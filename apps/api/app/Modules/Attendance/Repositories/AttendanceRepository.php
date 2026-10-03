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
     * @param  array{company_id?: int|null}  $filters  Only `company_id` is
     *   honored today — the attendance rows must be filtered down to
     *   employees belonging to the given company before the single-
     *   employee range check runs. Everything else is still scoped to the
     *   passed Employee.
     * @return Collection<int, Attendance>
     */
    public function forEmployeeInRange(Employee $employee, Carbon $start, Carbon $end, array $filters = []): Collection
    {
        $query = Attendance::query()
            ->where('employee_id', $employee->id)
            // Bare where() on the DATE column so MySQL keeps the
            // UNIQUE(employee_id, date) index — DATE()-wrapping via
            // whereDate() would rule the index out. Column stores a
            // pure 'Y-m-d' value so bare string comparisons range-scan
            // cleanly on both MySQL and SQLite.
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<=', $end->toDateString());

        $this->applyCompanyScope($query, $filters);

        return $query->get();
    }

    /**
     * @param  array{employee_id?: int, status?: string, date_from?: string, date_to?: string, company_id?: int|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Attendance::query()
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
            // date_from / date_to normalised to datetime bounds so the
            // Laravel `date` cast's "YYYY-MM-DD 00:00:00" storage on
            // SQLite doesn't fall outside a `<= 'YYYY-MM-DD'` comparison.
            // Matches the AttendanceStatsService fix (same bug family).
            ->when(
                $filters['date_from'] ?? null,
                fn (Builder $query, $date) => $query->where('date', '>=', \Carbon\Carbon::parse((string) $date)->startOfDay()),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn (Builder $query, $date) => $query->where('date', '<', \Carbon\Carbon::parse((string) $date)->startOfDay()->addDay()),
            );

        $this->applyCompanyScope($query, $filters);

        return $query->orderByDesc('date')->paginate($perPage);
    }

    /**
     * Soft Company Scoping: scope attendance rows to a given company via
     * a subquery against employees (any employee_id matching the
     * company_id passes). A subquery, not a JOIN, so the base query's
     * eager loads and pagination stay unambiguous.
     *
     * @param  Builder<Attendance>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyCompanyScope(Builder $query, array $filters): void
    {
        if (empty($filters['company_id'])) {
            return;
        }

        $query->whereIn(
            'employee_id',
            Employee::withTrashed()->select('id')->where('company_id', $filters['company_id']),
        );
    }
}
