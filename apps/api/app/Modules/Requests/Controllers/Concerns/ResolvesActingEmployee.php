<?php

declare(strict_types=1);

namespace App\Modules\Requests\Controllers\Concerns;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared by every controller in this module that acts as "the currently
 * authenticated employee" (self-service requests, the approval inbox, and
 * admin decision endpoints all need to know which Employee is behind the
 * acting User) — mirrors
 * App\Modules\Leaves\Controllers\EmployeeLeavesController::resolveEmployee().
 */
trait ResolvesActingEmployee
{
    protected function resolveActingEmployee(Request $request): Employee
    {
        $employee = $request->user()?->employee;

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Your account is not linked to an employee profile.',
            ]);
        }

        return $employee;
    }
}
