<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query building for every report in this module. Filtering by
 * department goes through `whereHas('employee.department', ...)` rather
 * than a raw SQL join: it compiles to an index-friendly correlated
 * subquery on every supported driver (MySQL in production, SQLite in
 * tests) without the portability problems of hand-written JOIN + string
 * concatenation (e.g. MySQL's CONCAT() vs SQLite's `||`). Eager loading
 * (`with`) on top of that is what actually prevents the N+1 that would
 * otherwise come from accessing ->employee/->department per row in
 * ReportService's row mappers.
 */
class ReportRepository
{
    /**
     * @param  array{from?: string, to?: string, employee_id?: int, department_id?: int}  $filters
     * @return Builder<Attendance>
     */
    public function attendanceQuery(array $filters): Builder
    {
        return Attendance::query()
            ->with(['employee.department'])
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('date', '<=', $to))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, $id) => $query->where('employee_id', $id))
            ->when(
                $filters['department_id'] ?? null,
                fn (Builder $query, $id) => $query->whereHas('employee', fn (Builder $employee) => $employee->where('department_id', $id))
            )
            ->orderBy('date')
            ->orderBy('employee_id');
    }

    /**
     * @param  array{from?: string, to?: string, employee_id?: int, type_id?: int, status?: string}  $filters
     * @return Builder<LeaveRequest>
     */
    public function leaveQuery(array $filters): Builder
    {
        return LeaveRequest::query()
            ->with(['employee.department', 'leaveType', 'reviewer'])
            // The range filters on start_date: a leave report for "March"
            // is read as "leaves that started in March", not every leave
            // that merely overlaps the month.
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('start_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('start_date', '<=', $to))
            ->when($filters['employee_id'] ?? null, fn (Builder $query, $id) => $query->where('employee_id', $id))
            ->when($filters['type_id'] ?? null, fn (Builder $query, $id) => $query->where('leave_type_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when(
                $filters['department_id'] ?? null,
                fn (Builder $query, $id) => $query->whereHas('employee', fn (Builder $employee) => $employee->where('department_id', $id))
            )
            ->orderByDesc('start_date');
    }
}
