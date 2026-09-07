<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Tasks\Resources\TaskResource;
use App\Modules\Tasks\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Self-service task endpoints under /me/tasks. The target employee is
 * always request()->user()->employee — never a client-supplied id —
 * mirroring EmployeeLeavesController's resolveEmployee() pattern.
 */
class MyTasksController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $this->resolveEmployee($request);
        $filters = [...$request->only(['status_id', 'priority_id', 'search']), 'assigned_to' => $employee->id];
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return TaskResource::collection($this->taskService->paginate($filters, $perPage));
    }

    public function created(Request $request): AnonymousResourceCollection
    {
        $employee = $this->resolveEmployee($request);
        $filters = [...$request->only(['status_id', 'priority_id', 'search']), 'created_by' => $employee->id];
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return TaskResource::collection($this->taskService->paginate($filters, $perPage));
    }

    private function resolveEmployee(Request $request): Employee
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
