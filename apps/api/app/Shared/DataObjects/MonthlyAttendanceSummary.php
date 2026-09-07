<?php

declare(strict_types=1);

namespace App\Shared\DataObjects;

/**
 * Read-only result of WorkingHoursCalculator::monthlySummary().
 *
 * `totalWorkingDays` is deliberately defined as the number of days already
 * resolved into present/absent/leave (i.e. it does not include workdays
 * later in the month that have not happened yet), so `expectedMinutes` and
 * `attendancePercentage` always describe the portion of the month that has
 * actually elapsed.
 */
final class MonthlyAttendanceSummary
{
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $totalWorkingDays,
        public readonly int $presentDays,
        public readonly int $absentDays,
        public readonly int $leaveDays,
        public readonly int $holidayDays,
        public readonly int $weekendDays,
        public readonly int $totalMinutes,
        public readonly int $expectedMinutes,
        public readonly int $differenceMinutes,
        public readonly int $overtimeMinutes,
        public readonly int $lateMinutes,
        public readonly int $earlyLeaveMinutes,
        public readonly float $attendancePercentage,
    ) {}

    /**
     * @return array<string, int|float>
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'total_working_days' => $this->totalWorkingDays,
            'present_days' => $this->presentDays,
            'absent_days' => $this->absentDays,
            'leave_days' => $this->leaveDays,
            'holiday_days' => $this->holidayDays,
            'weekend_days' => $this->weekendDays,
            'total_minutes' => $this->totalMinutes,
            'total_hours' => round($this->totalMinutes / 60, 2),
            'expected_minutes' => $this->expectedMinutes,
            'difference_minutes' => $this->differenceMinutes,
            'overtime_minutes' => $this->overtimeMinutes,
            'late_minutes' => $this->lateMinutes,
            'early_leave_minutes' => $this->earlyLeaveMinutes,
            'attendance_percentage' => $this->attendancePercentage,
        ];
    }
}
