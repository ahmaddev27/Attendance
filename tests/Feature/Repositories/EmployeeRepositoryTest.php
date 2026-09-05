<?php

use App\Models\Employee;
use App\Repositories\EmployeeRepository;

it('returns 0 when no employees exist', function () {
    expect(app(EmployeeRepository::class)->maxEmployeeNumber())->toBe(0);
});

it('returns the highest employee number', function () {
    Employee::factory()->create(['employee_number' => 1001]);
    Employee::factory()->create(['employee_number' => 1042]);
    Employee::factory()->create(['employee_number' => 1005]);

    expect(app(EmployeeRepository::class)->maxEmployeeNumber())->toBe(1042);
});

it('finds by employee number', function () {
    $emp = Employee::factory()->create(['employee_number' => 1010]);
    $found = app(EmployeeRepository::class)->findByNumber(1010);
    expect($found->id)->toBe($emp->id);
});

it('returns null when number not found', function () {
    expect(app(EmployeeRepository::class)->findByNumber(9999))->toBeNull();
});
