<?php

declare(strict_types=1);

namespace App\Modules\Requests\Services;

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Requests\Events\RequestApproved;
use App\Modules\Requests\Repositories\ApprovalRepository;
use App\Modules\Requests\Repositories\RequestRepository;
use App\Shared\Enums\ApprovalAction;
use App\Shared\Enums\RequestStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Advances (or terminates) a Request's workflow one decision at a time.
 * Every method locks the request row for the duration of its transaction
 * so two near-simultaneous decisions on the same request (e.g. an
 * approver double-clicking "approve", or an approve/forward race) can
 * never both apply — the same lockForUpdate-inside-transaction pattern
 * LeaveRequestService uses.
 */
class ApprovalService
{
    public function __construct(
        private readonly RequestRepository $requests,
        private readonly ApprovalRepository $approvals,
        private readonly ApproverResolver $resolver,
        private readonly NotificationService $notifier,
    ) {}

    public function approve(RequestModel $request, Employee $approver, ?string $comment = null): RequestModel
    {
        [$fresh, $nextStep] = DB::transaction(function () use ($request, $approver, $comment) {
            $locked = $this->requests->findForUpdate($request->id);
            $step = $this->guardActionable($locked, $approver);

            $this->approvals->create([
                'request_id' => $locked->id,
                'workflow_step_id' => $step->id,
                'approver_id' => $approver->id,
                'action' => ApprovalAction::Approved,
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $nextStep = $locked->requestType->workflow->nextStepAfter($step);

            if ($nextStep !== null) {
                $locked->update(['current_step_id' => $nextStep->id]);
            } else {
                $locked->update([
                    'status' => RequestStatus::Approved,
                    'completed_at' => now(),
                    'current_step_id' => null,
                ]);
            }

            return [$this->requests->findOrFail($locked->id), $nextStep];
        });

        // Notifications + events dispatched AFTER the transaction commits so
        // a listener that throws can't roll back the approval, and a broadcast
        // can't reach the frontend before the DB row it references exists.
        if ($nextStep === null) {
            RequestApproved::dispatch($fresh);
            $this->notifier->requestDecided($fresh);
        } else {
            // Non-terminal — ping the newly-active approvers so they can act
            // on the request without polling the inbox. Resolver returns
            // Employees; the notifier expects Users, so map and filter out
            // employees whose login user was never provisioned.
            $approvers = $this->resolver->resolve($nextStep, $fresh);
            $users = $approvers
                ->map(fn (Employee $employee) => $employee->user)
                ->filter(fn ($user) => $user instanceof User)
                ->values();
            $this->notifier->requestPendingApproval($fresh, $users);
        }

        return $fresh;
    }

    public function reject(RequestModel $request, Employee $approver, string $comment): RequestModel
    {
        return DB::transaction(function () use ($request, $approver, $comment) {
            $locked = $this->requests->findForUpdate($request->id);
            $step = $this->guardActionable($locked, $approver);

            if (! $step->can_reject) {
                throw ValidationException::withMessages([
                    'action' => 'This step does not allow rejection.',
                ]);
            }

            $this->approvals->create([
                'request_id' => $locked->id,
                'workflow_step_id' => $step->id,
                'approver_id' => $approver->id,
                'action' => ApprovalAction::Rejected,
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $locked->update([
                'status' => RequestStatus::Rejected,
                'completed_at' => now(),
            ]);

            $fresh = $this->requests->findOrFail($locked->id);
            $this->notifier->requestDecided($fresh);

            return $fresh;
        });
    }

    /**
     * Sends the request back to the employee for edits. The employee
     * re-enters the workflow from its first step via
     * RequestService::resubmit() once they've made changes.
     */
    public function return(RequestModel $request, Employee $approver, string $comment): RequestModel
    {
        return DB::transaction(function () use ($request, $approver, $comment) {
            $locked = $this->requests->findForUpdate($request->id);
            $step = $this->guardActionable($locked, $approver);

            if (! $step->can_return) {
                throw ValidationException::withMessages([
                    'action' => 'This step does not allow returning the request.',
                ]);
            }

            $this->approvals->create([
                'request_id' => $locked->id,
                'workflow_step_id' => $step->id,
                'approver_id' => $approver->id,
                'action' => ApprovalAction::Returned,
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $locked->update([
                'status' => RequestStatus::Returned,
                'current_step_id' => null,
            ]);

            $fresh = $this->requests->findOrFail($locked->id);
            $this->notifier->requestDecided($fresh);

            return $fresh;
        });
    }

    /**
     * Adds $forwardTo as an alternate approver for the *current* step —
     * it does not advance the workflow or change the request's status.
     * See Request::scopePendingForApprover() and
     * ApproverResolver::isAuthorized() for how the forwarded-to employee
     * is then recognized as authorized for up to 30 days.
     */
    public function forward(RequestModel $request, Employee $approver, Employee $forwardTo, string $comment): RequestModel
    {
        // Cycle guard: forwarding to yourself is a no-op that would still
        // record an approval row and re-notify — reject before touching
        // state or opening a transaction.
        if ($forwardTo->id === $approver->id) {
            throw ValidationException::withMessages([
                'forwarded_to_id' => 'لا يمكن تحويل الطلب لنفسك.',
            ]);
        }

        $fresh = DB::transaction(function () use ($request, $approver, $forwardTo, $comment) {
            $locked = $this->requests->findForUpdate($request->id);
            $step = $this->guardActionable($locked, $approver);

            if (! $step->can_forward) {
                throw ValidationException::withMessages([
                    'action' => 'This step does not allow forwarding the request.',
                ]);
            }

            $this->approvals->create([
                'request_id' => $locked->id,
                'workflow_step_id' => $step->id,
                'approver_id' => $approver->id,
                'action' => ApprovalAction::Forwarded,
                'comment' => $comment,
                'forwarded_to_id' => $forwardTo->id,
                'decided_at' => now(),
            ]);

            return $this->requests->findOrFail($locked->id);
        });

        // Notification dispatched AFTER commit (same pattern as approve())
        // so a broadcast/notifier failure can't roll back the forward. Only
        // notify when the target employee has a provisioned login user.
        if ($forwardTo->user instanceof User) {
            $this->notifier->requestPendingApproval($fresh, collect([$forwardTo->user]));
        }

        return $fresh;
    }

    /**
     * Guards common to every decision: the request must still be pending
     * on an actual step, and $approver must currently be authorized to
     * decide it. Checking "is this even actionable" before "is this
     * person allowed to act on it" matters — an already-decided request
     * with no current step would otherwise make an authorized approver
     * look like an unauthorized one (403 instead of the correct 422).
     */
    private function guardActionable(RequestModel $request, Employee $approver): WorkflowStep
    {
        if ($request->status !== RequestStatus::Pending || $request->currentStep === null) {
            throw ValidationException::withMessages([
                'status' => 'Only a pending request with an active step can be acted on.',
            ]);
        }

        if (! $this->resolver->isAuthorized($request, $approver)) {
            abort(403, 'You are not authorized to act on this request.');
        }

        // Self-approval guard: even if the approver resolver would grant
        // permission (e.g. the approver's role also covers this step),
        // the requester must never be the one who decides their own
        // request. This runs AFTER isAuthorized so a legitimate approver
        // still 403s on the wrong request instead of 422ing.
        if ($approver->id === $request->employee_id) {
            throw ValidationException::withMessages([
                'approver' => 'لا يمكنك الموافقة على طلبك الخاص.',
            ]);
        }

        return $request->currentStep;
    }
}
