<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Modules\Attendance\Repositories\AttendanceRepository;
use App\Modules\Attendance\Repositories\HolidayRepository;
use App\Shared\DataObjects\MonthlyAttendanceSummary;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Support\Carbon;

/**
 * The working-hours engine: turns a raw check-in/check-out pair into
 * late/early/overtime minutes and a status, and rolls a month of
 * attendance rows up into a single reporting summary.
 */
class WorkingHoursCalculator
{
    public function __construct(
        private readonly AttendanceRepository $attendances,
        private readonly HolidayRepository $holidays,
    ) {}

    /**
     * Compute and persist late minutes + status the moment an employee
     * scans in, so the row reflects "متأخر" immediately — not only after
     * they eventually scan out. The full compute (total/early/overtime)
     * still runs on check-out via computeForAttendance().
     *
     * Called from AttendanceService::checkIn right after the row is
     * saved with check_in_at.
     */
    public function stampCheckInStatus(Attendance $attendance, WorkSchedule $schedule): void
    {
        if (! $attendance->check_in_at) {
            return;
        }

        $lateMinutes = $this->calculateLateMinutes($attendance->check_in_at, $schedule);

        $attendance->forceFill([
            'late_minutes' => $lateMinutes,
            // The row is "متأخر" only when the raw lateness exceeds the
            // grace window — the grace governs the LABEL, not the
            // reported minutes (owner, 2026-10-05).
            'status' => $lateMinutes > $schedule->grace_late_minutes
                ? AttendanceStatus::Late
                : AttendanceStatus::Present,
        ])->save();
    }

    /**
     * Compute and persist total/late/early/overtime minutes and the final
     * status for a completed (checked-in and checked-out) attendance row.
     */
    public function computeForAttendance(Attendance $attendance, WorkSchedule $schedule): void
    {
        if (! $attendance->check_in_at || ! $attendance->check_out_at) {
            throw new \InvalidArgumentException(
                'Cannot compute working hours before both check-in and check-out are recorded.'
            );
        }

        $totalMinutes = (int) $attendance->check_in_at->diffInMinutes($attendance->check_out_at);
        $lateMinutes = $this->calculateLateMinutes($attendance->check_in_at, $schedule);
        $earlyLeaveMinutes = $this->calculateEarlyLeaveMinutes($attendance->check_out_at, $schedule);
        $overtimeMinutes = max(0, $totalMinutes - $schedule->expectedMinutes());

        // Status flips to Late/EarlyLeave only when the raw lateness
        // actually exceeds its grace window. The reported minutes
        // (late_minutes / early_leave_minutes) are always raw so the
        // admin sees the honest wall-clock delta regardless of grace.
        $status = match (true) {
            $lateMinutes > $schedule->grace_late_minutes => AttendanceStatus::Late,
            $earlyLeaveMinutes > $schedule->grace_early_leave_minutes => AttendanceStatus::EarlyLeave,
            default => AttendanceStatus::Present,
        };

        $attendance->forceFill([
            'total_minutes' => $totalMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $status,
        ])->save();
    }

    /**
     * Roll a calendar month up into totals: present/absent/leave/holiday/
     * weekend day counts plus aggregated minutes. Days later in the month
     * than "today" that have no attendance row yet are not counted as
     * absent — they simply haven't happened.
     */
    public function monthlySummary(Employee $employee, int $year, int $month): MonthlyAttendanceSummary
    {
        $schedule = $employee->workSchedule;

        if (! $schedule) {
            throw new \RuntimeException("Employee #{$employee->id} has no work schedule assigned.");
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $attendanceByDate = $this->attendances
            ->forEmployeeInRange($employee, $start, $end)
            ->keyBy(fn (Attendance $attendance) => $attendance->date->toDateString());

        $holidayDates = $this->holidays->datesInRange($start, $end);

        $presentDays = 0;
        $absentDays = 0;
        $leaveDays = 0;
        $holidayDays = 0;
        $weekendDays = 0;
        $totalMinutes = 0;
        $overtimeMinutes = 0;
        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateKey = $date->toDateString();

            if ($holidayDates->contains($dateKey)) {
                $holidayDays++;

                continue;
            }

            if (! $schedule->isWorkday($date)) {
                $weekendDays++;

                continue;
            }

            $attendance = $attendanceByDate->get($dateKey);

            if ($attendance) {
                match ($attendance->status) {
                    AttendanceStatus::OnLeave => $leaveDays++,
                    AttendanceStatus::Absent => $absentDays++,
                    default => $presentDays++,
                };

                if (! in_array($attendance->status, [AttendanceStatus::OnLeave, AttendanceStatus::Absent], true)) {
                    // For a closed session `total_minutes` was stamped
                    // at check-out and we use it verbatim. For an OPEN
                    // session on today, compute elapsed since check-in
                    // — otherwise the report reads '0 hours' for an
                    // employee who has been at their desk since 8 am.
                    // Only today's open session gets the fallback: an
                    // old row with check_in but no check_out is
                    // orphaned data that ops must correct manually.
                    if ($attendance->total_minutes !== null) {
                        $totalMinutes += $attendance->total_minutes;
                    } elseif (
                        $attendance->check_in_at
                        && $attendance->check_out_at === null
                        && $date->isSameDay($today)
                    ) {
                        $totalMinutes += (int) $attendance->check_in_at->diffInMinutes(now());
                    }
                    $overtimeMinutes += $attendance->overtime_minutes ?? 0;
                    $lateMinutes += $attendance->late_minutes ?? 0;
                    $earlyLeaveMinutes += $attendance->early_leave_minutes ?? 0;
                }

                continue;
            }

            if ($date->lte($today)) {
                $absentDays++;
            }

            // A workday later than today with no attendance yet: not counted.
        }

        $totalWorkingDays = $presentDays + $absentDays + $leaveDays;

        // Owner's rule 2026-10-05: when the schedule declares a monthly
        // hours target, use THAT as the "expected" against which actual
        // hours and the diff are measured — not the pro-rated
        // (workdays-so-far × hours/day). The schedule's monthly target
        // is the contractual anchor; prorating would under-report the
        // target and show a flattering diff that hides shortfalls.
        // Fallback to the pro-rated figure only when the schedule has
        // no monthly target configured (expectedMonthlyMinutes() = null).
        $monthlyTargetMinutes = $schedule->expectedMonthlyMinutes();
        $expectedMinutes = $monthlyTargetMinutes
            ?? ($totalWorkingDays * $schedule->expectedMinutes());

        $attendancePercentage = $totalWorkingDays > 0
            ? round(($presentDays / $totalWorkingDays) * 100, 2)
            : 0.0;

        return new MonthlyAttendanceSummary(
            year: $year,
            month: $month,
            totalWorkingDays: $totalWorkingDays,
            presentDays: $presentDays,
            absentDays: $absentDays,
            leaveDays: $leaveDays,
            holidayDays: $holidayDays,
            weekendDays: $weekendDays,
            totalMinutes: $totalMinutes,
            expectedMinutes: $expectedMinutes,
            expectedMonthlyMinutes: $monthlyTargetMinutes,
            differenceMinutes: $totalMinutes - $expectedMinutes,
            overtimeMinutes: $overtimeMinutes,
            lateMinutes: $lateMinutes,
            earlyLeaveMinutes: $earlyLeaveMinutes,
            attendancePercentage: $attendancePercentage,
        );
    }

    /**
     * Raw minutes late — the wall-clock delta between the schedule's
     * check-in time and the employee's actual check-in. Returns 0 for a
     * flexible schedule (no fixed check-in) or an on-time/early arrival.
     *
     * Owner's rule 2026-10-05: grace_late_minutes does NOT reduce the
     * number of minutes reported here. The grace governs only whether
     * the row's STATUS flips to "متأخر" (see computeForAttendance /
     * stampCheckInStatus). The admin wants the honest delta —
     * 9:55 against a 9:00 shift reads as 55 minutes, not 40.
     *
     * Pure compute, no side effects — safe to call from a nightly
     * recompute sweep or any other read-only consumer.
     */
    public function calculateLateMinutes(Carbon $checkInAt, WorkSchedule $schedule): int
    {
        if ($schedule->is_flexible || ! $schedule->check_in_time) {
            return 0;
        }

        // Owner's rule 2026-10-04: Gaza timezone (Asia/Gaza). The app runs
        // in UTC, so `$checkInAt` is a UTC Carbon and setting "09:00:00"
        // on it anchors to 09:00 UTC — in Gaza (UTC+2/+3) that's 11:00 or
        // 12:00 local. The admin page then shows a check-in close to the
        // scheduled time as hours-late, which is what owner's screenshot
        // captured. Convert both operands to the schedule's own timezone
        // first so the comparison is on the wall clock the admin typed.
        $scheduleTz = $schedule->timezone ?: 'Asia/Gaza';
        $localCheckIn = $checkInAt->copy()->setTimezone($scheduleTz);
        $scheduledCheckIn = $localCheckIn->copy()
            ->setTimeFromTimeString($schedule->check_in_time->format('H:i:s'));

        if ($localCheckIn->lessThanOrEqualTo($scheduledCheckIn)) {
            return 0;
        }

        return (int) $scheduledCheckIn->diffInMinutes($localCheckIn);
    }

    /**
     * Raw minutes left early — the wall-clock delta between the actual
     * check-out and the schedule's declared check-out time. Returns 0
     * for a flexible schedule or a departure at/after the scheduled time.
     *
     * Same owner's rule as calculateLateMinutes above:
     * grace_early_leave_minutes is NOT subtracted here; it only decides
     * whether the row's status flips to "انصراف مبكر".
     *
     * Pure compute, no side effects — safe to call from a nightly
     * recompute sweep or any other read-only consumer.
     */
    public function calculateEarlyLeaveMinutes(Carbon $checkOutAt, WorkSchedule $schedule): int
    {
        if ($schedule->is_flexible || ! $schedule->check_out_time) {
            return 0;
        }

        // Same timezone fix as calculateLateMinutes above — align both
        // operands to the schedule's local wall clock before comparing.
        $scheduleTz = $schedule->timezone ?: 'Asia/Gaza';
        $localCheckOut = $checkOutAt->copy()->setTimezone($scheduleTz);
        $scheduledCheckOut = $localCheckOut->copy()
            ->setTimeFromTimeString($schedule->check_out_time->format('H:i:s'));

        if ($localCheckOut->greaterThanOrEqualTo($scheduledCheckOut)) {
            return 0;
        }

        return (int) $localCheckOut->diffInMinutes($scheduledCheckOut);
    }
}
