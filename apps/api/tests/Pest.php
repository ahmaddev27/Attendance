<?php

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a
| specific PHPUnit test case class. By default, that class is this
| class unless you specify one manually.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Shared Attendance Test Helpers
|--------------------------------------------------------------------------
|
| Pest test files all run in the same PHP process, so plain functions
| shared across multiple Feature/Attendance*.php files live here rather
| than being redeclared per file.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function makeWorkSchedule(array $overrides = []): WorkSchedule
{
    return WorkSchedule::factory()->create($overrides);
}

function makeEmployeeWithSchedule(?WorkSchedule $schedule = null): Employee
{
    $schedule ??= makeWorkSchedule();

    return Employee::factory()->create(['work_schedule_id' => $schedule->id]);
}

/**
 * Authenticates the current test as a fresh super-admin user via the
 * sanctum guard. Ensures the 'super-admin' role and its permissions
 * exist first, so any route protected by spatie/laravel-permission
 * middleware (permission: manage-users, view-reports, etc.) passes.
 */
function actingAsAdmin(): User
{
    // Make sure the permission catalog exists in test DBs — RolePermissionSeeder
    // isn't in the RefreshDatabase pipeline, so we create only what we need.
    foreach (['view-reports', 'view-audit-logs', 'manage-users', 'manage-workflows', 'approve-leaves'] as $p) {
        \Spatie\Permission\Models\Permission::findOrCreate($p);
    }
    $role = \Spatie\Permission\Models\Role::findOrCreate('super-admin');
    $role->syncPermissions(\Spatie\Permission\Models\Permission::all());

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    test()->actingAs($user, 'sanctum');

    return $user;
}
