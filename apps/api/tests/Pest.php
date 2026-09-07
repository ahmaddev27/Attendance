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
 * Authenticates the current test as a fresh admin-ish user via the
 * sanctum guard, for hitting the auth:sanctum-protected Attendance routes.
 */
function actingAsAdmin(): User
{
    $user = User::factory()->create();
    test()->actingAs($user, 'sanctum');

    return $user;
}
