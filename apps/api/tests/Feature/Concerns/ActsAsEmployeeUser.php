<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Models\Employee;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Shared test helper for M4 (Leaves) self-service feature tests: the
 * /me/leaves endpoints resolve the acting employee from
 * request()->user()->employee, so exercising them needs a User row
 * actually linked to an Employee row — plain `actingAsAdmin()` (used by
 * the Attendance/Employees suites) creates a User with no employee_id and
 * is not enough here.
 */
trait ActsAsEmployeeUser
{
    protected function actingAsEmployeeUser(Employee $employee): User
    {
        $user = User::factory()->create(['employee_id' => $employee->id]);

        Sanctum::actingAs($user);

        return $user;
    }
}
