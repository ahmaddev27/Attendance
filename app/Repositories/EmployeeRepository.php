<?php

namespace App\Repositories;

use App\Models\Employee;

class EmployeeRepository
{
    public function maxEmployeeNumber(): int
    {
        return (int) Employee::max('employee_number');
    }

    public function findByNumber(int $number): ?Employee
    {
        return Employee::where('employee_number', $number)->first();
    }

    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh();
    }
}
