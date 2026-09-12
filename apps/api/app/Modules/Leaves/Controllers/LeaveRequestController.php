<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Modules\Leaves\Requests\AdminLeaveActionRequest;
use App\Modules\Leaves\Requests\SubmitLeaveRequestRequest;
use App\Modules\Leaves\Requests\UploadLeaveAttachmentRequest;
use App\Modules\Leaves\Resources\LeaveRequestResource;
use App\Modules\Leaves\Services\LeaveAttachmentService;
use App\Modules\Leaves\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        private readonly LeaveAttachmentService $leaveAttachments,
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

    /**
     * Admin submit-on-behalf attachment upload. Same private-disk path
     * scheme as the self-service endpoint — rooted at the admin's OWN
     * user id — which SubmitLeaveRequestRequest's `attachment_path` rule
     * already authorizes for whoever is currently signed in. The route
     * is gated on `permission:approve-leaves` (routes/api.php).
     */
    public function uploadAttachment(UploadLeaveAttachmentRequest $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $result = $this->leaveAttachments->store($admin, $request->file('file'));

        return response()->json(['data' => $result], 201);
    }

    /**
     * Signed-URL attachment download. The `signed` middleware validates
     * the URL signature; on top of that we require:
     *   - the caller is authenticated (auth:sanctum) — no anonymous
     *     enumeration of leaked signed links;
     *   - the caller is EITHER the owning employee's user OR carries
     *     the `approve-leaves` permission (HR reviewer).
     * The stored file lives on the private `local` disk, streamed as a
     * download to keep the browser out of the raw filesystem path.
     */
    public function downloadAttachment(Request $request, LeaveRequest $leaveRequest): StreamedResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null, 401);

        abort_unless($leaveRequest->attachment_path !== null, 404);

        $isOwner = $leaveRequest->employee?->user_id === $user->id;
        $isReviewer = false;

        try {
            $isReviewer = $user->hasPermissionTo('approve-leaves');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            // Missing permission definition == user does not have it.
        }

        abort_unless($isOwner || $isReviewer, 403, 'You do not have permission to download this attachment.');

        $disk = Storage::disk(LeaveAttachmentService::DISK);
        abort_unless($disk->exists($leaveRequest->attachment_path), 404);

        return $disk->download(
            $leaveRequest->attachment_path,
            basename($leaveRequest->attachment_path),
        );
    }
}
