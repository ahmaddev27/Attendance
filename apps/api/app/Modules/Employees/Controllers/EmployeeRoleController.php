<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Employees\Requests\AssignEmployeeRoleRequest;
use App\Modules\Employees\Services\EmployeeRoleService;
use Illuminate\Http\JsonResponse;

class EmployeeRoleController extends Controller
{
    public function __construct(
        private readonly EmployeeRoleService $roles,
    ) {}

    /**
     * `PUT /api/employees/{employee}/role` — replaces the role of the
     * employee's login account.
     */
    public function update(AssignEmployeeRoleRequest $request, Employee $employee): JsonResponse
    {
        $user = $this->roles->assign($employee, $request->validated('role'), $request->user());

        return response()->json([
            'data' => [
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'role' => $user->roles->first()?->name,
            ],
        ]);
    }
}
