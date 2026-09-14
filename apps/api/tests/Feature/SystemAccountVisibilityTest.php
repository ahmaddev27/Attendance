<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Modules\Attendance\Repositories\EmployeeScanPinRepository;
use App\Modules\Leaves\Services\LeaveBalanceService;
use App\Shared\Enums\EmployeeStatus;

/**
 * Migration 2026_09_20_100008 gives every super-admin an employee record
 * numbered 900000 and up, so approvals and tasks (which reference employees)
 * can reach them. That record is an account, not a member of staff, so it
 * must stay out of staff lists, counts and reports.
 */
function systemAccountEmployee(): Employee
{
    return Employee::factory()->create([
        'employee_number' => Employee::SYSTEM_NUMBER_RANGE_START,
        'first_name' => 'System',
        'last_name' => 'Administrator',
        'status' => EmployeeStatus::Active,
    ]);
}

test('the employees list leaves out system accounts', function () {
    actingAsAdmin();
    $system = systemAccountEmployee();
    $staff = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    $ids = collect($this->getJson('/api/employees?per_page=100')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($staff->id)->not->toContain($system->id);
});

test('dashboard employee counts leave out system accounts', function () {
    actingAsAdmin();
    systemAccountEmployee();
    Employee::factory()->count(2)->create(['status' => EmployeeStatus::Active]);

    $this->getJson('/api/admin/dashboard/kpis')
        ->assertOk()
        ->assertJsonPath('data.employees.total', 2)
        ->assertJsonPath('data.employees.active', 2);
});

test('the monthly attendance report leaves out system accounts', function () {
    actingAsAdmin();
    $system = systemAccountEmployee();
    $staff = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    foreach ([$system, $staff] as $employee) {
        Attendance::factory()->create(['employee_id' => $employee->id, 'date' => '2026-08-10']);
    }

    $ids = collect($this->getJson('/api/admin/reports/attendance/monthly?year=2026&month=8')->assertOk()->json('data'))
        ->pluck('employee_id');

    expect($ids)->toContain($staff->id)->not->toContain($system->id);
});

test('bulk scan PIN issuing skips system accounts', function () {
    $system = systemAccountEmployee();
    $staff = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    $seen = [];
    app(EmployeeScanPinRepository::class)->chunkActiveEmployeesWithoutPin(100, function ($employees) use (&$seen): void {
        array_push($seen, ...$employees->pluck('id')->all());
    });

    expect($seen)->toContain($staff->id)->not->toContain($system->id);
});

test('the annual leave rollover skips system accounts', function () {
    $system = systemAccountEmployee();
    $staff = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    LeaveType::factory()->create(['is_balance_based' => true, 'is_active' => true, 'default_annual_entitlement' => 14]);

    app(LeaveBalanceService::class)->rollOverYear(2027);

    expect(LeaveBalance::where('employee_id', $staff->id)->exists())->toBeTrue()
        ->and(LeaveBalance::where('employee_id', $system->id)->exists())->toBeFalse();
});

test('system accounts are kept out of the search index', function () {
    expect(systemAccountEmployee()->shouldBeSearchable())->toBeFalse()
        ->and(Employee::factory()->create()->shouldBeSearchable())->toBeTrue();
});
