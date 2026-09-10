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
 * For every row with `check_in_at` set and `check_out_at` null, look up
 * the employee's WorkSchedule and, if the shift's end time on the
 * attendance date has already passed, stamp `check_out_at` at the
 * schedule's declared shift-end (NOT `now()`) — the point of the shift
 * is when the employee was expected to leave, not when the scheduler
 * happened to run. A note marker distinguishes auto-closed rows from
 * ones the employee closed themselves, so ops can audit later.
 *
 * Employees on a flexible schedule (`is_flexible=true`) are skipped —
 * their "end of shift" is a moving target, so an automatic stamp would
 * be wrong more often than not. Same for employees with no
 * work_schedule_id: we don't have anything to base the closing time on.
 *
 * Sessions older than 18 hours are already blocked from a self-close
 * by AttendanceService::checkOut, so an admin correction is the only
 * remaining path there — this command runs every 15 minutes so it
 * catches shifts within the same day they end.
 */
class AutoCloseForgottenAttendance extends Command
{
    protected $signature = 'taqat:auto-close-attendance
                            {--dry : print what would be closed without writing}';

    protected $description = 'Auto-close open attendance sessions where the shift end time has already passed.';

    public function __construct(
        private readonly WorkingHoursCalculator $calculator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry');
        $now = Carbon::now();

        $open = Attendance::query()
            ->with(['employee.workSchedule'])
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            // Bound at 48h — anything older is out of the "same-day
            // forgot" case this command handles; ops must intervene.
            ->where('check_in_at', '>=', $now->copy()->subHours(48))
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
                    $attendance->fill([
                        'check_out_at' => $shiftEnd,
                        'notes' => trim(($attendance->notes ?? '')
                            ."\n[نظام] أُغلقت الجلسة تلقائياً عند نهاية الدوام لعدم تسجيل الانصراف."),
                    ])->save();

                    $this->calculator->computeForAttendance($attendance, $schedule);
                });

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

        $this->info(sprintf('Closed %d session(s); skipped %d.', $closed, $skipped));

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

        return Carbon::parse($date->toDateString().' '.$endTime, $schedule->timezone ?: config('app.timezone'));
    }
}
