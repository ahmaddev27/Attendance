<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Modules\Leaves\Services\LeaveBalanceService;
use Tests\Feature\Concerns\ActsAsEmployeeUser;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(ActsAsEmployeeUser::class, CreatesSuperAdmin::class);

test('accrual creates a balance row for every active, balance-based leave type and skips the rest', function () {
    $annual = LeaveType::factory()->create(['is_active' => true, 'is_balance_based' => true, 'default_annual_entitlement' => 21]);
    $sick = LeaveType::factory()->create(['is_active' => true, 'is_balance_based' => true, 'default_annual_entitlement' => 14]);
    $emergency = LeaveType::factory()->notBalanceBased()->create(['is_active' => true]);
    $inactiveAnnual = LeaveType::factory()->create(['is_active' => false, 'is_balance_based' => true]);

    $employee = Employee::factory()->create();
    $year = (int) now()->year;

    app(LeaveBalanceService::class)->accrueForYear($employee, $year);

    $this->assertDatabaseHas('leave_balances', ['employee_id' => $employee->id, 'leave_type_id' => $annual->id, 'year' => $year, 'entitlement' => 21]);
    $this->assertDatabaseHas('leave_balances', ['employee_id' => $employee->id, 'leave_type_id' => $sick->id, 'year' => $year, 'entitlement' => 14]);
    $this->assertDatabaseMissing('leave_balances', ['employee_id' => $employee->id, 'leave_type_id' => $emergency->id]);
    $this->assertDatabaseMissing('leave_balances', ['employee_id' => $employee->id, 'leave_type_id' => $inactiveAnnual->id]);
});

test('accrual is idempotent — running it twice does not duplicate or reset an existing balance', function () {
    $annual = LeaveType::factory()->create(['is_active' => true, 'is_balance_based' => true, 'default_annual_entitlement' => 21]);
    $employee = Employee::factory()->create();
    $year = (int) now()->year;

    app(LeaveBalanceService::class)->accrueForYear($employee, $year);

    $balance = LeaveBalance::query()->where('employee_id', $employee->id)->where('leave_type_id', $annual->id)->firstOrFail();
    $balance->update(['used' => 5]);

    app(LeaveBalanceService::class)->accrueForYear($employee, $year);

    expect(LeaveBalance::query()->where('employee_id', $employee->id)->where('leave_type_id', $annual->id)->count())->toBe(1)
        ->and((float) $balance->refresh()->used)->toBe(5.0);
});

test('an admin can adjust an employee leave balance entitlement', function () {
    $this->actingAsSuperAdmin();

    $leaveType = LeaveType::factory()->create();
    $employee = Employee::factory()->create();
    $year = (int) now()->year;

    $response = $this->postJson('/api/leave-balances/adjust', [
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => $year,
        'delta' => 3,
        'reason' => 'Compensation for public holiday worked.',
    ]);

    $response->assertOk()->assertJsonPath('data.entitlement', 24);

    $this->assertDatabaseHas('leave_balances', [
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => $year,
        'entitlement' => (float) $leaveType->default_annual_entitlement + 3,
    ]);
});

test('adjustment requires a reason', function () {
    $this->actingAsSuperAdmin();

    $leaveType = LeaveType::factory()->create();
    $employee = Employee::factory()->create();

    $response = $this->postJson('/api/leave-balances/adjust', [
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => (int) now()->year,
        'delta' => 3,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['reason']);
});

test('the remaining accessor subtracts used and pending from entitlement plus carry-over', function () {
    $balance = LeaveBalance::factory()->create([
        'entitlement' => 21,
        'carry_over_from_previous' => 2,
        'used' => 5,
        'pending' => 3,
    ]);

    expect($balance->remaining)->toBe(15.0);
});

test('the available accessor is unbounded for a leave type that allows a negative balance', function () {
    $leaveType = LeaveType::factory()->allowingNegativeBalance()->create();
    $balance = LeaveBalance::factory()->create([
        'leave_type_id' => $leaveType->id,
        'entitlement' => 5,
        'used' => 10,
        'pending' => 0,
    ]);

    expect($balance->remaining)->toBe(-5.0)
        ->and(is_infinite($balance->available))->toBeTrue();
});

test('an employee only sees their own balances via the self-service endpoint', function () {
    $employee = Employee::factory()->create();
    $otherEmployee = Employee::factory()->create();
    $year = (int) now()->year;

    LeaveBalance::factory()->create(['employee_id' => $employee->id, 'year' => $year]);
    LeaveBalance::factory()->create(['employee_id' => $otherEmployee->id, 'year' => $year]);

    $this->actingAsEmployeeUser($employee);

    $response = $this->getJson('/api/me/leaves/balances');

    $response->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee_id', $employee->id);
});

test('an admin can look up a specific employee\'s balances', function () {
    $this->actingAsSuperAdmin();

    $employee = Employee::factory()->create();
    $year = (int) now()->year;
    LeaveBalance::factory()->create(['employee_id' => $employee->id, 'year' => $year]);

    $response = $this->getJson("/api/leave-balances?employee_id={$employee->id}&year={$year}");

    $response->assertOk()->assertJsonCount(1, 'data');
});
