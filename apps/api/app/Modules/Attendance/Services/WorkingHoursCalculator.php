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
        $lateMinutes = $this->lateMinutes($attendance->check_in_at, $schedule);
        $earlyLeaveMinutes = $this->earlyLeaveMinutes($attendance->check_out_at, $schedule);
        $overtimeMinutes = max(0, $totalMinutes - $schedule->expectedMinutes());

        $status = match (true) {
            $lateMinutes > 0 => AttendanceStatus::Late,
            $earlyLeaveMinutes > 0 => AttendanceStatus::EarlyLeave,
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
                    $totalMinutes += $attendance->total_minutes ?? 0;
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
        $expectedMinutes = $totalWorkingDays * $schedule->expectedMinutes();
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
            differenceMinutes: $totalMinutes - $expectedMinutes,
            overtimeMinutes: $overtimeMinutes,
            lateMinutes: $lateMinutes,
            earlyLeaveMinutes: $earlyLeaveMinutes,
            attendancePercentage: $attendancePercentage,
        );
    }

    /**
     * Minutes late, net of grace, or 0 for a flexible schedule (no fixed
     * check-in time) or an on-time/early arrival.
     */
    private function lateMinutes(Carbon $checkInAt, WorkSchedule $schedule): int
    {
        if ($schedule->is_flexible || ! $schedule->check_in_time) {
            return 0;
        }

        $scheduledCheckIn = $checkInAt->copy()->setTimeFromTimeString($schedule->check_in_time->format('H:i:s'));

        if ($checkInAt->lessThanOrEqualTo($scheduledCheckIn)) {
            return 0;
        }

        return max(0, (int) $scheduledCheckIn->diffInMinutes($checkInAt) - $schedule->grace_late_minutes);
    }

    /**
     * Minutes left early, net of grace, or 0 for a flexible schedule (no
     * fixed check-out time) or a departure at/after the scheduled time.
     */
    private function earlyLeaveMinutes(Carbon $checkOutAt, WorkSchedule $schedule): int
    {
        if ($schedule->is_flexible || ! $schedule->check_out_time) {
            return 0;
        }

        $scheduledCheckOut = $checkOutAt->copy()->setTimeFromTimeString($schedule->check_out_time->format('H:i:s'));

        if ($checkOutAt->greaterThanOrEqualTo($scheduledCheckOut)) {
            return 0;
        }

        return max(0, (int) $checkOutAt->diffInMinutes($scheduledCheckOut) - $schedule->grace_early_leave_minutes);
    }
}
