<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Employees\Requests\StoreEmployeeRequest;
use App\Modules\Employees\Requests\UpdateEmployeeRequest;
use App\Modules\Employees\Resources\EmployeeResource;
use App\Modules\Employees\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search', 'department_id', 'team_id', 'position_id',
            'status', 'employment_type', 'direct_manager_id',
        ]);

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return EmployeeResource::collection($this->employeeService->paginate($filters, $perPage));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeService->create($request->validated());

        return (new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $employee = $this->employeeService->update($employee, $request->validated());

        return new EmployeeResource($employee->load(['position', 'department', 'team', 'directManager']));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->employeeService->softDelete($employee);

        return response()->json(['message' => 'Employee deleted.']);
    }

    /**
     * Restore a soft-deleted employee. Deliberately typed as `int` rather
     * than an `Employee` route binding — Laravel's default implicit model
     * binding excludes trashed models, which is exactly the record this
     * endpoint needs to find.
     */
    public function restore(int $employee): EmployeeResource
    {
        $restored = $this->employeeService->restore($employee);

        return new EmployeeResource($restored->load(['position', 'department', 'team', 'directManager']));
    }
}
