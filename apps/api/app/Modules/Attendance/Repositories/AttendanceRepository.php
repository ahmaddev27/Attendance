<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Repositories;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceRepository
{
    public function findByEmployeeAndDate(Employee $employee, Carbon $date): ?Attendance
    {
        // Bare where() on a DATE column — MySQL only uses the
        // UNIQUE(employee_id, date) index when the column is not wrapped
        // in a DATE()/YEAR()/etc. function call. whereDate() would emit
        // DATE(`date`) = ? and force a full-table scan.
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date->toDateString())
            ->first();
    }

    /**
     * The most recent check-in that has not yet been checked out, if any.
     * Used by AttendanceService::checkOut() so a session that started
     * before midnight can still be closed after the calendar day rolls
     * over.
     */
    public function findOpenForEmployee(Employee $employee): ?Attendance
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->latest('date')
            ->first();
    }

    public function latestForEmployee(Employee $employee): ?Attendance
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->latest('date')
            ->first();
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function forEmployeeInRange(Employee $employee, Carbon $start, Carbon $end): Collection
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            // Bare where() on the DATE column so MySQL keeps the
            // UNIQUE(employee_id, date) index — DATE()-wrapping via
            // whereDate() would rule the index out. Column stores a
            // pure 'Y-m-d' value so bare string comparisons range-scan
            // cleanly on both MySQL and SQLite.
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<=', $end->toDateString())
            ->get();
    }

    /**
     * @param  array{employee_id?: int, status?: string, date_from?: string, date_to?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Attendance::query()
            // withTrashed() on the `employee` relation so historical
            // attendance rows keep showing the former employee's name
            // instead of collapsing to null when the person leaves and
            // gets soft-deleted. The row itself is the audit trail;
            // dropping the join just because the person quit would erase
            // months of past-tense reporting from the admin table.
            ->with([
                'employee' => fn ($q) => $q->withTrashed(),
                'checkInDevice',
                'checkOutDevice',
            ])
            ->when($filters['employee_id'] ?? null, fn (Builder $query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            // Bare where() so the composite (employee_id, status, date)
            // index actually gets used — DATE() wrappers disqualify it.
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->where('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->where('date', '<=', $date))
            ->orderByDesc('date')
            ->paginate($perPage);
    }
}
