<?php

declare(strict_types=1);

namespace App\Modules\Requests\Services;

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Requests\Events\RequestSubmitted;
use App\Modules\Requests\Repositories\RequestRepository;
use App\Modules\Workflow\Repositories\RequestTypeRepository;
use App\Shared\Enums\RequestStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates a Request's life up to the point ApprovalService takes
 * over (submit -> first decision), plus cancellation from any
 * still-open state.
 */
class RequestService
{
    public function __construct(
        private readonly RequestRepository $requests,
        private readonly RequestTypeRepository $requestTypes,
        private readonly RequestNumberGenerator $numberGenerator,
        private readonly FormSchemaValidator $formValidator,
        private readonly ApproverResolver $approverResolver,
        private readonly NotificationService $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listAdmin(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->requests->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listForEmployee(Employee $employee, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->requests->paginateForEmployee($employee, $filters, $perPage);
    }

    /**
     * @param  list<string>  $roleNames  Only used when $employee is null —
     *                                    lets a bootstrap super-admin (no
     *                                    linked Employee) still see requests
     *                                    routed via SpecificRole steps.
     */
    public function pendingForApprover(?Employee $employee, int $perPage = 25, array $roleNames = []): LengthAwarePaginator
    {
        return $this->requests->paginatePendingForApprover($employee, $perPage, $roleNames);
    }

    public function find(int $id): RequestModel
    {
        return $this->requests->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data  request_type_id, form_data
     */
    public function submit(Employee $employee, array $data): RequestModel
    {
        $requestType = $this->requestTypes->findOrFail((int) $data['request_type_id']);

        if (! $requestType->is_active) {
            throw ValidationException::withMessages([
                'request_type_id' => 'This request type is not currently active.',
            ]);
        }

        $formData = $data['form_data'] ?? [];

        $this->formValidator->validate($requestType, $formData);

        [$request, $firstStep] = DB::transaction(function () use ($employee, $requestType, $formData) {
            $requestNumber = $this->numberGenerator->generate();
            $firstStep = $requestType->workflow->firstStep();
            $hasWorkflow = $firstStep !== null;

            $created = $this->requests->create([
                'request_number' => $requestNumber,
                'employee_id' => $employee->id,
                'request_type_id' => $requestType->id,
                'form_data' => $formData,
                'status' => $hasWorkflow ? RequestStatus::Pending : RequestStatus::Approved,
                'current_step_id' => $firstStep?->id,
                'submitted_at' => now(),
                'completed_at' => $hasWorkflow ? null : now(),
            ]);

            return [$created, $firstStep];
        });

        // Post-commit fan-out (mirrors ApprovalService::approve's pattern) —
        // event + first-step approver notifications live outside the
        // transaction so a notifier failure can't roll back the create and
        // a broadcast can't reach the frontend before the DB row exists.
        RequestSubmitted::dispatch($request);

        if ($firstStep !== null) {
            $this->notifyFirstStepApprovers($request, $firstStep);
        }

        return $request;
    }

    /**
     * A Returned request re-entering the workflow from the top, after the
     * employee has edited it — mirrors submit()'s routing (first step, or
     * immediate auto-approval for a stepless workflow) but keeps the
     * original request_number rather than minting a new one.
     *
     * @param  array<string, mixed>  $formData
     */
    public function resubmit(RequestModel $request, array $formData): RequestModel
    {
        if ($request->status !== RequestStatus::Returned) {
            throw ValidationException::withMessages([
                'status' => 'Only a returned request can be resubmitted.',
            ]);
        }

        $requestType = $request->requestType;

        $this->formValidator->validate($requestType, $formData);

        [$fresh, $firstStep] = DB::transaction(function () use ($request, $requestType, $formData) {
            $firstStep = $requestType->workflow->firstStep();
            $hasWorkflow = $firstStep !== null;

            $request->update([
                'form_data' => $formData,
                'status' => $hasWorkflow ? RequestStatus::Pending : RequestStatus::Approved,
                'current_step_id' => $firstStep?->id,
                'submitted_at' => now(),
                'completed_at' => $hasWorkflow ? null : now(),
            ]);

            return [$this->requests->findOrFail($request->id), $firstStep];
        });

        RequestSubmitted::dispatch($fresh);

        if ($firstStep !== null) {
            $this->notifyFirstStepApprovers($fresh, $firstStep);
        }

        return $fresh;
    }

    /**
     * Fan out the "new request awaiting your approval" notification to
     * every approver resolved for the workflow's first step. Kept here
     * (rather than as a listener on RequestSubmitted) so submit/resubmit
     * share exactly one implementation and the notification is guaranteed
     * to fire — no queue-configuration dependency and no silent drop when
     * a listener isn't registered.
     */
    private function notifyFirstStepApprovers(RequestModel $request, WorkflowStep $firstStep): void
    {
        $approvers = $this->approverResolver->resolve($firstStep, $request);

        $users = $approvers
            ->map(fn (Employee $employee) => $employee->user)
            ->filter(fn ($user) => $user instanceof User)
            ->values();

        $this->notifier->requestPendingApproval($request, $users);
    }

    /**
     * Cancels a request that has not yet been decided. Ownership is
     * enforced by the caller (see MyRequestsController), the same
     * division of responsibility EmployeeLeavesController uses for
     * leave-request cancellation.
     */
    public function cancel(RequestModel $request): RequestModel
    {
        return DB::transaction(function () use ($request) {
            $locked = $this->requests->findForUpdate($request->id);

            if (! $locked->canBeCancelled()) {
                throw ValidationException::withMessages([
                    'status' => 'This request can no longer be cancelled.',
                ]);
            }

            $locked->update([
                'status' => RequestStatus::Cancelled,
                'completed_at' => now(),
                'current_step_id' => null,
            ]);

            return $this->requests->findOrFail($locked->id);
        });
    }
}
