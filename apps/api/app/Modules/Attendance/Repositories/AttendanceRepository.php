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
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
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
            // whereDate() (not whereBetween on the raw strings) so the
            // range's last day isn't dropped: a `date`-cast column is
            // stored as a full 'Y-m-d H:i:s' string, which — on a loosely
            // typed connection such as SQLite — sorts *after* a bare
            // 'Y-m-d' upper bound and would otherwise be excluded.
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
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
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date))
            ->orderByDesc('date')
            ->paginate($perPage);
    }
}
