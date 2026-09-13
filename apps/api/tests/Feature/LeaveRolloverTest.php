<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Modules\Leaves\Services\LeaveBalanceService;
use App\Shared\Enums\EmployeeStatus;

/**
 * @param  array{entitlement?: float|int, used?: float|int, pending?: float|int, carried?: float|int}  $amounts
 */
function seedRolloverBalance(Employee $employee, LeaveType $type, int $year, array $amounts = []): LeaveBalance
{
    return LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'year' => $year,
        'entitlement' => $amounts['entitlement'] ?? 21,
        'used' => $amounts['used'] ?? 0,
        'pending' => $amounts['pending'] ?? 0,
        'carry_over_from_previous' => $amounts['carried'] ?? 0,
    ]);
}

function rolloverBalanceFor(Employee $employee, LeaveType $type, int $year): ?LeaveBalance
{
    return LeaveBalance::query()
        ->where('employee_id', $employee->id)
        ->where('leave_type_id', $type->id)
        ->where('year', $year)
        ->first();
}

function annualLeaveType(array $overrides = []): LeaveType
{
    return LeaveType::factory()->create([
        'is_active' => true,
        'is_balance_based' => true,
        'default_annual_entitlement' => 21,
        'carry_over_max_days' => 5,
        ...$overrides,
    ]);
}

test('unused days carry over up to the cap and the new year starts at the default entitlement', function () {
    $annual = annualLeaveType();
    $capped = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $underCap = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    seedRolloverBalance($capped, $annual, 2026, ['used' => 10, 'pending' => 2]);
    seedRolloverBalance($underCap, $annual, 2026, ['used' => 18]);

    app(LeaveBalanceService::class)->rollOverYear(2027);

    expect((float) rolloverBalanceFor($capped, $annual, 2027)->carry_over_from_previous)->toBe(5.0)
        ->and((float) rolloverBalanceFor($capped, $annual, 2027)->entitlement)->toBe(21.0)
        ->and((float) rolloverBalanceFor($underCap, $annual, 2027)->carry_over_from_previous)->toBe(3.0);
});

test('days carried into last year count towards what can carry again', function () {
    $annual = annualLeaveType(['carry_over_max_days' => 10]);
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    seedRolloverBalance($employee, $annual, 2026, ['entitlement' => 21, 'carried' => 4, 'used' => 20]);

    app(LeaveBalanceService::class)->rollOverYear(2027);

    expect((float) rolloverBalanceFor($employee, $annual, 2027)->carry_over_from_previous)->toBe(5.0);
});

test('a type without a cap carries nothing and an overdrawn balance carries nothing', function () {
    $uncapped = annualLeaveType(['carry_over_max_days' => null]);
    $capped = annualLeaveType(['code' => 'capped-overdrawn']);
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    seedRolloverBalance($employee, $uncapped, 2026, ['used' => 1]);
    seedRolloverBalance($employee, $capped, 2026, ['used' => 25]);

    app(LeaveBalanceService::class)->rollOverYear(2027);

    expect((float) rolloverBalanceFor($employee, $uncapped, 2027)->carry_over_from_previous)->toBe(0.0)
        ->and((float) rolloverBalanceFor($employee, $capped, 2027)->carry_over_from_previous)->toBe(0.0);
});

test('only staff on balance-based active leave types get a new year row', function () {
    $annual = annualLeaveType();
    $inactiveType = annualLeaveType(['code' => 'retired-type', 'is_active' => false]);
    $unpaid = annualLeaveType(['code' => 'unpaid-type', 'is_balance_based' => false]);
    $active = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $onLeave = Employee::factory()->create(['status' => EmployeeStatus::from('on_leave')]);
    $terminated = Employee::factory()->create(['status' => EmployeeStatus::from('terminated')]);

    app(LeaveBalanceService::class)->rollOverYear(2027);

    expect(rolloverBalanceFor($active, $annual, 2027))->not->toBeNull()
        ->and(rolloverBalanceFor($onLeave, $annual, 2027))->not->toBeNull()
        ->and(rolloverBalanceFor($terminated, $annual, 2027))->toBeNull()
        ->and(rolloverBalanceFor($active, $inactiveType, 2027))->toBeNull()
        ->and(rolloverBalanceFor($active, $unpaid, 2027))->toBeNull();
});

test('an employee with no balance last year starts the new year with nothing carried', function () {
    $annual = annualLeaveType();
    $newHire = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    app(LeaveBalanceService::class)->rollOverYear(2027);

    expect((float) rolloverBalanceFor($newHire, $annual, 2027)->carry_over_from_previous)->toBe(0.0)
        ->and((float) rolloverBalanceFor($newHire, $annual, 2027)->entitlement)->toBe(21.0);
});

test('re-running recomputes the carry and keeps what already happened in the new year', function () {
    $annual = annualLeaveType();
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    $lastYear = seedRolloverBalance($employee, $annual, 2026, ['used' => 19]);

    app(LeaveBalanceService::class)->rollOverYear(2027);
    rolloverBalanceFor($employee, $annual, 2027)->update(['used' => 3, 'pending' => 1, 'entitlement' => 25]);

    // A leave from last year gets rejected after the rollover, freeing days.
    $lastYear->update(['used' => 16]);
    app(LeaveBalanceService::class)->rollOverYear(2027);

    $newYear = rolloverBalanceFor($employee, $annual, 2027);
    expect((float) $newYear->carry_over_from_previous)->toBe(5.0)
        ->and((float) $newYear->used)->toBe(3.0)
        ->and((float) $newYear->pending)->toBe(1.0)
        ->and((float) $newYear->entitlement)->toBe(25.0)
        ->and(LeaveBalance::query()->where('employee_id', $employee->id)->where('year', 2027)->count())->toBe(1);
});

test('the command opens the requested year and rejects a nonsense year', function () {
    $annual = annualLeaveType();
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    $this->artisan('leaves:annual-rollover', ['--year' => 2027])->assertExitCode(0);
    $this->artisan('leaves:annual-rollover', ['--year' => 'soon'])->assertExitCode(2);

    expect(rolloverBalanceFor($employee, $annual, 2027))->not->toBeNull();
});

test('the rollover is scheduled', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('leaves:annual-rollover')
        ->assertExitCode(0);
});
