<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Models\Employee;
use App\Modules\Employees\Repositories\EmployeeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepository $employees,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->employees->paginate($filters, $perPage);
    }

    public function find(int $id): Employee
    {
        return $this->employees->findOrFail($id);
    }

    /**
     * Create an employee, atomically assigning the next sequential
     * employee_number.
     *
     * The max(employee_number) lookup is locked FOR UPDATE inside the
     * transaction so two concurrent create requests cannot both read the
     * same max and insert the same next number (the v1 race-condition
     * lesson this system was rebuilt to avoid).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Employee
    {
        // employee_number is always server-generated — never trust a
        // client-supplied value here, even if one slipped through.
        unset($data['employee_number']);

        return DB::transaction(function () use ($data) {
            $data['employee_number'] = $this->employees->maxEmployeeNumberForUpdate() + 1;

            return $this->employees->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        unset($data['employee_number']);

        if (array_key_exists('direct_manager_id', $data) && $data['direct_manager_id'] !== null) {
            $this->assertManagerIsNotSelf($employee, (int) $data['direct_manager_id']);
        }

        return $this->employees->update($employee, $data);
    }

    public function assignManager(Employee $employee, ?int $managerId): Employee
    {
        if ($managerId !== null) {
            $this->assertManagerIsNotSelf($employee, $managerId);
        }

        return $this->employees->update($employee, ['direct_manager_id' => $managerId]);
    }

    public function softDelete(Employee $employee): bool
    {
        return $this->employees->softDelete($employee);
    }

    public function restore(int $id): Employee
    {
        $employee = $this->employees->findTrashedOrFail($id);

        return $this->employees->restore($employee);
    }

    private function assertManagerIsNotSelf(Employee $employee, int $managerId): void
    {
        if ($employee->exists && $managerId === $employee->id) {
            throw ValidationException::withMessages([
                'direct_manager_id' => 'An employee cannot be their own direct manager.',
            ]);
        }
    }
}
