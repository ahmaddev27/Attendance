<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Modules\Notifications\Notifications\OpenSessionReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Reminds employees ~30 minutes BEFORE their shift-end that they still
 * have an open attendance session, so they get a chance to scan out
 * themselves instead of relying on AutoCloseForgottenAttendance to
 * stamp `check_out_at` at the shift-end after the fact.
 *
 * The auto-close is honest (it stamps at the declared shift-end, not
 * `now()`), but the employee never sees it happen — the push gives
 * them a chance to close the session on their own timing.
 *
 * Selection window:
 *   - open attendance row (check_in_at set, check_out_at null)
 *   - non-flexible schedule (flexible schedules have no fixed shift-end,
 *     so "30 minutes before end" is meaningless)
 *   - shift-end is between +20 and +40 minutes from now
 *
 * The 20-40 window is a superset of the "exactly 30 min out" target so
 * the every-15-min scheduler cadence catches every open session at
 * least once (13min or 27min after the last tick still lands inside
 * the window). Cache::add() memoizes per-attendance so back-to-back
 * ticks don't double-fire.
 */
class NotifyOpenAttendanceSessions extends Command
{
    protected $signature = 'taqat:notify-open-sessions
                            {--dry : print what would be notified without dispatching}';

    protected $description = 'Push a reminder to any employee ~30 minutes before their shift ends with an open attendance session.';

    /**
     * Lower/upper bound of the "about to end" window, in minutes.
     * Kept a bit wider than the 15-min scheduler cadence so no session
     * slips through when a tick runs a few seconds late.
     */
    private const int WINDOW_MIN_MINUTES = 20;

    private const int WINDOW_MAX_MINUTES = 40;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry');
        $now = Carbon::now();

        // Bounded scope: only today's open rows can plausibly be within
        // 40 minutes of their shift-end. Anything older is either
        // already past shift-end (AutoCloseForgottenAttendance's job)
        // or was opened yesterday and forgotten (admin correction path).
        $open = Attendance::query()
            ->with(['employee.user.pushTokens:id,user_id', 'employee.workSchedule'])
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->where('date', $now->toDateString())
            ->get();

        if ($open->isEmpty()) {
            $this->info('No open sessions found for today.');

            return self::SUCCESS;
        }

        $notified = 0;
        $skipped = 0;

        foreach ($open as $attendance) {
            $employee = $attendance->employee;
            $schedule = $employee?->workSchedule;
            $user = $employee?->user;

            if (! $employee instanceof Employee
                || ! $schedule instanceof WorkSchedule
                || ! $user instanceof User) {
                $skipped++;
                continue;
            }

            if ($schedule->is_flexible) {
                $skipped++;
                continue;
            }

            $shiftEnd = $this->shiftEndFor($schedule, $attendance->date);

            if ($shiftEnd === null) {
                $skipped++;
                continue;
            }

            $minutesUntilEnd = $now->diffInMinutes($shiftEnd, false);

            if ($minutesUntilEnd < self::WINDOW_MIN_MINUTES
                || $minutesUntilEnd > self::WINDOW_MAX_MINUTES) {
                $skipped++;
                continue;
            }

            // 6-hour TTL is long enough that a scheduler stall or a
            // re-queue can't cause a duplicate ping later in the same
            // shift, but short enough that tomorrow's open session for
            // the same employee gets a fresh key.
            $memoKey = "open-session-reminded:{$attendance->id}";
            if (! Cache::add($memoKey, true, now()->addHours(6))) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    'DRY: would notify user %d (employee %d, attendance %d), shift ends %s (in ~%dm)',
                    $user->id,
                    $employee->id,
                    $attendance->id,
                    $shiftEnd->toDateTimeString(),
                    (int) $minutesUntilEnd,
                ));
                $notified++;
                continue;
            }

            try {
                Notification::send(
                    $user,
                    new OpenSessionReminderNotification($attendance->id),
                );
                $notified++;
            } catch (\Throwable $e) {
                // Free the memo key so the next tick can retry — we
                // don't want a transient push-gateway blip to swallow
                // the reminder for the whole shift.
                Cache::forget($memoKey);

                Log::warning('NotifyOpenAttendanceSessions dispatch failed', [
                    'attendance_id' => $attendance->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $this->info(sprintf('Notified %d session(s); skipped %d.', $notified, $skipped));

        return self::SUCCESS;
    }

    /**
     * Combine the attendance date with the schedule's `check_out_time`
     * into a full datetime in the schedule's own timezone. Mirrors the
     * helper in AutoCloseForgottenAttendance so both commands agree on
     * "when does the shift end."
     */
    private function shiftEndFor(WorkSchedule $schedule, ?Carbon $date): ?Carbon
    {
        if (! $date || ! $schedule->check_out_time) {
            return null;
        }

        $endTime = Carbon::parse($schedule->check_out_time)->format('H:i:s');

        return Carbon::parse(
            $date->toDateString().' '.$endTime,
            $schedule->timezone ?: config('app.timezone'),
        );
    }
}
