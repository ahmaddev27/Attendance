<?php

declare(strict_types=1);

namespace App\Modules\Requests\Services;

use App\Models\Employee;
use App\Models\Request as RequestModel;
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

    public function pendingForApprover(Employee $employee, int $perPage = 25): LengthAwarePaginator
    {
        return $this->requests->paginatePendingForApprover($employee, $perPage);
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

        return DB::transaction(function () use ($employee, $requestType, $formData) {
            $requestNumber = $this->numberGenerator->generate();
            $firstStep = $requestType->workflow->firstStep();
            $hasWorkflow = $firstStep !== null;

            $request = $this->requests->create([
                'request_number' => $requestNumber,
                'employee_id' => $employee->id,
                'request_type_id' => $requestType->id,
                'form_data' => $formData,
                'status' => $hasWorkflow ? RequestStatus::Pending : RequestStatus::Approved,
                'current_step_id' => $firstStep?->id,
                'submitted_at' => now(),
                'completed_at' => $hasWorkflow ? null : now(),
            ]);

            RequestSubmitted::dispatch($request);

            return $request;
        });
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

        return DB::transaction(function () use ($request, $requestType, $formData) {
            $firstStep = $requestType->workflow->firstStep();
            $hasWorkflow = $firstStep !== null;

            $request->update([
                'form_data' => $formData,
                'status' => $hasWorkflow ? RequestStatus::Pending : RequestStatus::Approved,
                'current_step_id' => $firstStep?->id,
                'submitted_at' => now(),
                'completed_at' => $hasWorkflow ? null : now(),
            ]);

            $fresh = $this->requests->findOrFail($request->id);

            RequestSubmitted::dispatch($fresh);

            return $fresh;
        });
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
