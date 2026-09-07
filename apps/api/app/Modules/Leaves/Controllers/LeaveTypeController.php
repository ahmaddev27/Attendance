<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Modules\Leaves\Requests\StoreLeaveTypeRequest;
use App\Modules\Leaves\Requests\UpdateLeaveTypeRequest;
use App\Modules\Leaves\Resources\LeaveTypeResource;
use App\Modules\Leaves\Services\LeaveTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveTypeController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly LeaveTypeService $leaveTypes,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['is_active', 'is_balance_based', 'search']);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return LeaveTypeResource::collection($this->leaveTypes->paginate($filters, $perPage));
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $leaveType = $this->leaveTypes->create($request->validated());

        return (new LeaveTypeResource($leaveType))->response()->setStatusCode(201);
    }

    public function show(LeaveType $leave_type): LeaveTypeResource
    {
        return new LeaveTypeResource($leave_type);
    }

    public function update(UpdateLeaveTypeRequest $request, LeaveType $leave_type): LeaveTypeResource
    {
        return new LeaveTypeResource($this->leaveTypes->update($leave_type, $request->validated()));
    }

    public function destroy(LeaveType $leave_type): JsonResponse
    {
        $this->leaveTypes->delete($leave_type);

        return response()->json(null, 204);
    }
}
