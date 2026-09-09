<?php

declare(strict_types=1);

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Modules\Organization\Requests\StoreDepartmentRequest;
use App\Modules\Organization\Requests\UpdateDepartmentRequest;
use App\Modules\Organization\Resources\DepartmentResource;
use App\Modules\Organization\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartmentController extends Controller
{
    private const DEFAULT_PER_PAGE = 100;

    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['active', 'is_active', 'search']);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return DepartmentResource::collection($this->departments->paginate($filters, $perPage));
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->departments->create($request->validated());

        return (new DepartmentResource($department->load(['manager', 'parent'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Department $department): DepartmentResource
    {
        return new DepartmentResource($department->load(['manager', 'parent', 'children']));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $department = $this->departments->update($department, $request->validated());

        return new DepartmentResource($department->load(['manager', 'parent']));
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->departments->delete($department);

        return response()->json(['message' => 'Department deleted.']);
    }
}
