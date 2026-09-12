<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rolls a calendar month up into one row per employee, ready for the
 * admin's month-end payroll/attendance review.
 *
 * Deliberately does NOT reuse WorkingHoursCalculator::monthlySummary —
 * that helper walks the schedule/holiday calendar day by day for a single
 * employee, which is O(days * employees) when we need every employee for
 * a whole month. Here we do it in two grouped queries (attendance rows
 * grouped by employee+status, then employee metadata), which stays flat
 * regardless of headcount.
 */
class AttendanceReportService
{
    /**
     * @return Collection<int, array{
     *   employee_id: int,
     *   employee_number: int,
     *   full_name: string,
     *   department: ?string,
     *   present_days: int,
     *   late_days: int,
     *   absent_days: int,
     *   leave_days: int,
     *   total_minutes: int,
     *   overtime_minutes: int,
     *   late_minutes: int,
     * }>
     */
    public function monthly(int $year, int $month, ?int $departmentId = null, ?int $employeeId = null): Collection
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        // One row per (employee, status) — aggregated minutes summed at the
        // same time so we don't need a second pass.
        $rows = Attendance::query()
            ->selectRaw(
                'employee_id, status,
                 COUNT(*) as days,
                 COALESCE(SUM(total_minutes), 0) as total_minutes,
                 COALESCE(SUM(overtime_minutes), 0) as overtime_minutes,
                 COALESCE(SUM(late_minutes), 0) as late_minutes'
            )
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($departmentId, function ($q) use ($departmentId): void {
                $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $departmentId));
            })
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->groupBy('employee_id', 'status')
            ->get();

        // Employees eligible for the report — the group excludes anyone
        // with zero attendance rows in the range, so pull the employee
        // list separately to make sure inactive-but-relevant staff are
        // still shown with zeros (matches how payroll expects the report).
        $employees = Employee::query()
            ->with('department:id,name')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($employeeId, fn ($q) => $q->where('id', $employeeId))
            ->whereIn('id', $rows->pluck('employee_id')->unique())
            ->get()
            ->keyBy('id');

        return $rows
            ->groupBy('employee_id')
            ->map(function (Collection $employeeRows, int $employeeId) use ($employees) {
                $employee = $employees->get($employeeId);
                if (! $employee) {
                    return null;
                }

                $bucket = fn (AttendanceStatus $s): int => (int) ($employeeRows->firstWhere('status', $s->value)?->days ?? 0);

                return [
                    'employee_id' => $employeeId,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department?->name,
                    'present_days' => $bucket(AttendanceStatus::Present)
                        + $bucket(AttendanceStatus::Late)
                        + $bucket(AttendanceStatus::EarlyLeave)
                        + $bucket(AttendanceStatus::Remote)
                        + $bucket(AttendanceStatus::BusinessMission),
                    'late_days' => $bucket(AttendanceStatus::Late) + $bucket(AttendanceStatus::EarlyLeave),
                    'absent_days' => $bucket(AttendanceStatus::Absent),
                    'leave_days' => $bucket(AttendanceStatus::OnLeave),
                    'total_minutes' => (int) $employeeRows->sum('total_minutes'),
                    'overtime_minutes' => (int) $employeeRows->sum('overtime_minutes'),
                    'late_minutes' => (int) $employeeRows->sum('late_minutes'),
                ];
            })
            ->filter()
            ->values();
    }
}
