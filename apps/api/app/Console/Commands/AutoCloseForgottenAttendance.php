<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Modules\Attendance\Services\WorkingHoursCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-closes open attendance sessions the employee forgot to close.
 *
 * Runs ONCE per day shortly after midnight Asia/Gaza, scanning rows
 * whose attendance.date is the previous Gaza day (or older) and that
 * are still open. Running once at midnight — not every 15 minutes
 * through the shift — is deliberate: employees who legitimately work
 * past their declared shift-end (overtime) must have the whole day to
 * scan out themselves. If a mid-day sweep stamps check_out_at at
 * shift-end while they are still at their desk, the extra hours are
 * lost. Any row still open at the end of the Gaza day is treated as
 * a true "forgot to scan out" case and auto-closed at the schedule's
 * declared shift-end.
 *
 * For every eligible row, look up the employee's WorkSchedule and,
 * if the shift's end time on the attendance date has already passed,
 * stamp `check_out_at` at the schedule's declared shift-end (NOT
 * `now()`) — the point of the shift is when the employee was expected
 * to leave, not when the scheduler happened to run. A note marker
 * distinguishes auto-closed rows from ones the employee closed
 * themselves, so ops can audit later.
 *
 * Employees on a flexible schedule (`is_flexible=true`) are skipped —
 * their "end of shift" is a moving target, so an automatic stamp would
 * be wrong more often than not. Same for employees with no
 * work_schedule_id: we don't have anything to base the closing time on.
 *
 * All timestamps are interpreted in Asia/Gaza; the schedule's own
 * timezone wins when it is set, and the Gaza fallback kicks in
 * otherwise. config('app.timezone') is deliberately NOT used — the
 * container may run UTC/Amman but attendance always belongs to Gaza.
 */
class AutoCloseForgottenAttendance extends Command
{
    protected $signature = 'taqat:auto-close-attendance
                            {--dry : print what would be closed without writing}';

    protected $description = 'Auto-close open attendance sessions whose Gaza day has ended with no scan-out.';

    /**
     * Every date, "now" and schedule-fallback inside this command is
     * evaluated against Asia/Gaza so a container running Asia/Amman or
     * UTC does not shift the day boundary and mis-attribute rows.
     */
    private const string ATTENDANCE_TIMEZONE = 'Asia/Gaza';

    public function __construct(
        private readonly WorkingHoursCalculator $calculator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry');
        $now = Carbon::now(self::ATTENDANCE_TIMEZONE);
        $yesterday = $now->copy()->subDay()->toDateString();

        // Target rows whose Gaza day has already ended: date <= yesterday
        // in Gaza. A 7-day lower bound still protects us from scanning
        // ancient rows if the scheduler was down and never swept them.
        $open = Attendance::query()
            ->with(['employee.workSchedule'])
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->whereDate('date', '<=', $yesterday)
            ->where('check_in_at', '>=', $now->copy()->subDays(7))
            ->get();

        if ($open->isEmpty()) {
            $this->info('No open sessions found.');

            return self::SUCCESS;
        }

        $closed = 0;
        $skipped = 0;

        foreach ($open as $attendance) {
            $employee = $attendance->employee;
            $schedule = $employee?->workSchedule;

            if (! $employee instanceof Employee || ! $schedule instanceof WorkSchedule) {
                $skipped++;
                continue;
            }

            if ($schedule->is_flexible) {
                $skipped++;
                continue;
            }

            $shiftEnd = $this->shiftEndFor($schedule, $attendance->date);

            if ($shiftEnd === null || $shiftEnd->greaterThan($now)) {
                // Shift end hasn't arrived yet — leave it alone.
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    'DRY: would close attendance #%d (employee %d) at %s',
                    $attendance->id,
                    $employee->id,
                    $shiftEnd->toDateTimeString(),
                ));
                $closed++;
                continue;
            }

            try {
                DB::transaction(function () use ($attendance, $shiftEnd, $schedule): void {
                    // forceFill bypasses $fillable to make the auto-close
                    // immune to a future refactor that drops check_out_at
                    // / notes from the mass-assignable list.
                    $attendance->forceFill([
                        'check_out_at' => $shiftEnd,
                        'notes' => trim(($attendance->notes ?? '')
                            ."\n[نظام] أُغلقت الجلسة تلقائياً عند نهاية الدوام لعدم تسجيل الانصراف."),
                    ])->save();

                    $this->calculator->computeForAttendance($attendance, $schedule);
                });

                // Confirm the write actually landed before we count it as
                // closed — a silently-rolled-back transaction here would
                // otherwise be invisible until an employee complains that
                // their anṣrāf never appeared.
                $attendance->refresh();
                if ($attendance->check_out_at === null) {
                    Log::warning('AutoClose transaction committed but check_out_at is still null', [
                        'attendance_id' => $attendance->id,
                        'employee_id' => $employee->id,
                    ]);
                    $skipped++;
                    continue;
                }

                Log::info('AutoClose stamped shift-end check-out', [
                    'attendance_id' => $attendance->id,
                    'employee_id' => $employee->id,
                    'check_out_at' => $attendance->check_out_at->toDateTimeString(),
                ]);
                $this->line(sprintf(
                    'Closed attendance #%d (employee %d) at %s',
                    $attendance->id,
                    $employee->id,
                    $shiftEnd->toDateTimeString(),
                ));
                $closed++;
            } catch (\Throwable $e) {
                Log::warning('AutoClose failed on attendance row', [
                    'attendance_id' => $attendance->id,
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $message = sprintf('Closed %d session(s); skipped %d.', $closed, $skipped);
        $this->info($message);
        Log::info('AutoCloseForgottenAttendance run complete', [
            'closed' => $closed,
            'skipped' => $skipped,
            'dry' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    /**
     * Combine the attendance date with the schedule's `check_out_time`
     * into a full datetime in the schedule's own timezone. Returns null
     * if the schedule has no configured check-out time.
     */
    private function shiftEndFor(WorkSchedule $schedule, ?Carbon $date): ?Carbon
    {
        if (! $date || ! $schedule->check_out_time) {
            return null;
        }

        // The schedule's check_out_time is cast as 'datetime:H:i' — the
        // Carbon instance carries today's date, so extract just the H:i.
        $endTime = Carbon::parse($schedule->check_out_time)->format('H:i:s');

        return Carbon::parse(
            $date->toDateString().' '.$endTime,
            $schedule->timezone ?: self::ATTENDANCE_TIMEZONE,
        );
    }
}
