<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Modules\Leaves\Requests\AdminLeaveActionRequest;
use App\Modules\Leaves\Requests\SubmitLeaveRequestRequest;
use App\Modules\Leaves\Resources\LeaveRequestResource;
use App\Modules\Leaves\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Admin-facing leave request endpoints. The route parameter is named
 * `leave_request` (Laravel's default apiResource wildcard for
 * 'leave-requests'), so every bound method below uses that same
 * snake_case name to match it.
 */
class LeaveRequestController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly LeaveRequestService $leaveRequests,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['employee_id', 'leave_type_id', 'status', 'start_date', 'end_date']);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return LeaveRequestResource::collection($this->leaveRequests->listAdmin($filters, $perPage));
    }

    /**
     * Admin submitting a leave request on behalf of an employee —
     * requires employee_id, unlike EmployeeLeavesController::store() which
     * always targets the authenticated user's own employee record.
     */
    public function store(SubmitLeaveRequestRequest $request): JsonResponse
    {
        $employeeId = $request->validated('employee_id');

        if (empty($employeeId)) {
            throw ValidationException::withMessages([
                'employee_id' => 'employee_id is required when submitting on behalf of an employee.',
            ]);
        }

        $employee = Employee::query()->findOrFail($employeeId);

        $leaveRequest = $this->leaveRequests->submit($employee, $request->validated());

        return (new LeaveRequestResource($leaveRequest))->response()->setStatusCode(201);
    }

    public function show(LeaveRequest $leave_request): LeaveRequestResource
    {
        return new LeaveRequestResource($this->leaveRequests->find($leave_request->id));
    }

    /**
     * Deletes a request that never left draft. Anything already
     * submitted must be cancelled instead — see
     * LeaveRequestService::deleteDraft().
     */
    public function destroy(LeaveRequest $leave_request): JsonResponse
    {
        $this->leaveRequests->deleteDraft($leave_request);

        return response()->json(null, 204);
    }

    public function approve(AdminLeaveActionRequest $request, LeaveRequest $leave_request): LeaveRequestResource
    {
        /** @var User $admin */
        $admin = $request->user();

        return new LeaveRequestResource($this->leaveRequests->approve($leave_request, $admin));
    }

    public function reject(AdminLeaveActionRequest $request, LeaveRequest $leave_request): LeaveRequestResource
    {
        /** @var User $admin */
        $admin = $request->user();

        return new LeaveRequestResource($this->leaveRequests->reject(
            $leave_request,
            $admin,
            (string) $request->validated('rejection_reason'),
        ));
    }

    public function cancel(LeaveRequest $leave_request): LeaveRequestResource
    {
        return new LeaveRequestResource($this->leaveRequests->cancel($leave_request, isAdmin: true));
    }
}
