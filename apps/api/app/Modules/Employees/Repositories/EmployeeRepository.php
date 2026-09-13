<?php

declare(strict_types=1);

namespace App\Modules\Employees\Repositories;

use App\Models\Employee;
use App\Models\User;
use App\Shared\Enums\EmployeeStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class EmployeeRepository
{
    /**
     * Relations eager-loaded on every read to keep the resource layer
     * free of N+1 queries.
     *
     * @var list<string>
     */
    private const WITH = ['position', 'department', 'team', 'directManager', 'workSchedule'];

    /**
     * Numbers from here up belong to system accounts: migration
     * 2026_09_20_100008 gives each super-admin an employee record at 900000+,
     * so regular hires must keep counting below it.
     */
    private const SYSTEM_NUMBER_RANGE_START = 900000;

    /**
     * Reserve the next employee_number. Must be called inside a transaction
     * (see EmployeeService::create) so concurrent creates serialize on the
     * locked range instead of racing to the same number.
     *
     * Soft-deleted employees keep their number — the unique index covers
     * trashed rows — and a login user can hold a number with no employee
     * row (the seeded admin). Ignoring either made the next create collide
     * and fail with a 500 once the most recent employee had been deleted.
     */
    public function nextEmployeeNumberForUpdate(): int
    {
        $next = (int) Employee::withTrashed()
            ->where('employee_number', '<', self::SYSTEM_NUMBER_RANGE_START)
            ->lockForUpdate()
            ->max('employee_number') + 1;

        $takenByUsers = User::query()
            ->where('employee_number', '>=', $next)
            ->where('employee_number', '<', self::SYSTEM_NUMBER_RANGE_START)
            ->orderBy('employee_number')
            ->pluck('employee_number');

        foreach ($takenByUsers as $taken) {
            if ((int) $taken !== $next) {
                break;
            }
            $next++;
        }

        if ($next >= self::SYSTEM_NUMBER_RANGE_START) {
            throw new RuntimeException('Regular employee numbers have reached the reserved system range.');
        }

        return $next;
    }

    public function findByNumber(int $employeeNumber): ?Employee
    {
        return Employee::query()->where('employee_number', $employeeNumber)->first();
    }

    public function findActiveByNumber(int $employeeNumber): ?Employee
    {
        return Employee::query()
            ->with('user')
            ->where('employee_number', $employeeNumber)
            ->where('status', EmployeeStatus::Active)
            ->first();
    }

    public function findOrFail(int $id): Employee
    {
        return Employee::query()->with(self::WITH)->findOrFail($id);
    }

    public function findTrashedOrFail(int $id): Employee
    {
        return Employee::onlyTrashed()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Employee
    {
        return Employee::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Employee $employee, array $attributes): Employee
    {
        $employee->update($attributes);

        return $employee->refresh();
    }

    public function softDelete(Employee $employee): bool
    {
        return (bool) $employee->delete();
    }

    public function restore(Employee $employee): Employee
    {
        $employee->restore();

        return $employee->refresh();
    }

    /**
     * Filtered, paginated employee listing for the index endpoint.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Employee::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderBy('last_name')->orderBy('first_name')->paginate($perPage);
    }

    /**
     * @param  Builder<Employee>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['department_id', 'team_id', 'position_id', 'status', 'employment_type', 'direct_manager_id'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['search'])) {
            $this->applySearch($query, (string) $filters['search']);
        }
    }

    /**
     * @param  Builder<Employee>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $driver = $query->getConnection()->getDriverName();

        // MySQL's `||` operator means logical OR (unless PIPES_AS_CONCAT is
        // enabled), while SQLite/Postgres use it for string concatenation —
        // so the full-name match needs a driver-specific expression to stay
        // correct on both the MySQL production database and the SQLite
        // in-memory database used by the test suite.
        $fullNameExpression = $driver === 'sqlite' || $driver === 'pgsql'
            ? "(first_name || ' ' || last_name)"
            : "CONCAT(first_name, ' ', last_name)";

        $query->where(function (Builder $q) use ($search, $fullNameExpression) {
            $like = "%{$search}%";

            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhereRaw("{$fullNameExpression} LIKE ?", [$like]);

            if (is_numeric($search)) {
                $q->orWhere('employee_number', (int) $search);
            }
        });
    }
}
