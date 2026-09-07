<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Modules\Leaves\Requests\SubmitLeaveRequestRequest;
use App\Modules\Leaves\Resources\LeaveBalanceResource;
use App\Modules\Leaves\Resources\LeaveRequestResource;
use App\Modules\Leaves\Services\LeaveBalanceService;
use App\Modules\Leaves\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Self-service leave endpoints under /me/leaves. The target employee is
 * always request()->user()->employee — never a client-supplied id — so an
 * employee can only ever see or act on their own leave data.
 */
class EmployeeLeavesController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly LeaveRequestService $leaveRequests,
        private readonly LeaveBalanceService $leaveBalances,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $this->resolveEmployee($request);
        $filters = $request->only(['leave_type_id', 'status', 'start_date', 'end_date']);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return LeaveRequestResource::collection($this->leaveRequests->listForEmployee($employee, $filters, $perPage));
    }

    public function balances(Request $request): AnonymousResourceCollection
    {
        $employee = $this->resolveEmployee($request);
        $year = $request->integer('year') ?: (int) now()->year;

        return LeaveBalanceResource::collection($this->leaveBalances->findByEmployee($employee->id, $year));
    }

    public function store(SubmitLeaveRequestRequest $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $leaveRequest = $this->leaveRequests->submit($employee, $request->validated());

        return (new LeaveRequestResource($leaveRequest))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, LeaveRequest $leave_request): LeaveRequestResource
    {
        $employee = $this->resolveEmployee($request);

        if ($leave_request->employee_id !== $employee->id) {
            abort(403, 'You may not cancel another employee\'s leave request.');
        }

        return new LeaveRequestResource($this->leaveRequests->cancel($leave_request, isAdmin: false));
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
