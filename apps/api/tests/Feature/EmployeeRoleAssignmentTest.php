<?php

use App\Models\Employee;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Until the employees page could assign roles, nobody could be given the
 * management, manager or recruitment roles without server access.
 */
function employeeWithLogin(string $role = 'employee'): Employee
{
    $employee = Employee::factory()->create();
    $user = User::factory()->create(['employee_id' => $employee->id, 'employee_number' => $employee->employee_number]);
    $employee->forceFill(['user_id' => $user->id])->save();
    $user->assignRole($role);

    return $employee->fresh();
}

function roleNamesOf(Employee $employee): array
{
    return User::query()->where('employee_id', $employee->id)->firstOrFail()->getRoleNames()->all();
}

test('an admin replaces the role of an employee login account', function () {
    actingAsAdmin();
    $employee = employeeWithLogin('employee');

    $this->putJson("/api/employees/{$employee->id}/role", ['role' => 'recruiter'])
        ->assertOk()
        ->assertJsonPath('data.role', 'recruiter');

    expect(roleNamesOf($employee))->toBe(['recruiter']);
});

test('the employees list shows each employee role', function () {
    actingAsAdmin();
    $employee = employeeWithLogin('sales');

    $row = collect($this->getJson('/api/employees?per_page=100')->assertOk()->json('data'))->firstWhere('id', $employee->id);

    expect($row['role'])->toBe('sales');
});

test('unknown roles are rejected', function () {
    actingAsAdmin();
    $employee = employeeWithLogin();

    $this->putJson("/api/employees/{$employee->id}/role", ['role' => 'owner'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

test('an employee without a login account cannot be given a role yet', function () {
    actingAsAdmin();
    $employee = Employee::factory()->create();

    $this->putJson("/api/employees/{$employee->id}/role", ['role' => 'sales'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

test('only a super-admin can grant super-admin', function () {
    $manager = User::factory()->create();
    Role::findOrCreate('user-manager', 'web')->givePermissionTo(Permission::findOrCreate('manage-users', 'web'));
    $manager->assignRole('user-manager');
    Sanctum::actingAs($manager);
    $employee = employeeWithLogin();

    $this->putJson("/api/employees/{$employee->id}/role", ['role' => 'super-admin'])->assertForbidden();

    expect(roleNamesOf($employee))->toBe(['employee']);
});

test('the last active super-admin keeps the role', function () {
    Role::findOrCreate('super-admin', 'web');
    $onlySuperAdmin = employeeWithLogin('super-admin');
    $actor = User::query()->where('employee_id', $onlySuperAdmin->id)->firstOrFail();
    Sanctum::actingAs($actor);

    $this->putJson("/api/employees/{$onlySuperAdmin->id}/role", ['role' => 'employee'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);

    expect(roleNamesOf($onlySuperAdmin))->toBe(['super-admin']);
});

test('system accounts cannot have their role changed', function () {
    actingAsAdmin();
    $system = Employee::factory()->create(['employee_number' => Employee::SYSTEM_NUMBER_RANGE_START]);

    $this->putJson("/api/employees/{$system->id}/role", ['role' => 'employee'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

test('assigning roles needs manage-users', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $employee = employeeWithLogin();

    $this->putJson("/api/employees/{$employee->id}/role", ['role' => 'sales'])->assertForbidden();
});

test('reset-password no longer changes roles', function () {
    actingAsAdmin();
    $employee = employeeWithLogin('employee');

    $this->postJson("/api/employees/{$employee->id}/reset-password", ['role' => 'super-admin'])->assertOk();

    expect(roleNamesOf($employee))->toBe(['employee']);
});
