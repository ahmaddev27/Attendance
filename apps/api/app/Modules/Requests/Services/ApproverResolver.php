<?php

declare(strict_types=1);

namespace App\Modules\Requests\Services;

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Shared\Enums\ApprovalAction;
use App\Shared\Enums\ApproverType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Turns a WorkflowStep's approver_type/approver_ref into the actual
 * Employee(s) allowed to decide a given Request at that step.
 */
class ApproverResolver
{
    /**
     * Mirrors Request::FORWARD_WINDOW_DAYS — kept as a separate constant
     * since that one is private to the model and this resolver is the
     * other place the same 30-day window is enforced (for the single-row
     * authorization check, as opposed to the model's bulk inbox query).
     */
    private const FORWARD_WINDOW_DAYS = 30;

    /**
     * @return Collection<int, Employee>
     */
    public function resolve(WorkflowStep $step, RequestModel $request): Collection
    {
        return match ($step->approver_type) {
            ApproverType::DirectManager => $this->wrap($request->employee->directManager),
            ApproverType::DepartmentManager => $this->wrap($request->employee->department?->manager),
            ApproverType::SpecificEmployee => $this->wrap(Employee::query()->find($step->approver_ref)),
            ApproverType::SpecificRole => $this->resolveByRole($step->approver_ref),
            ApproverType::FormField => $this->wrap($this->resolveFormFieldEmployee($step, $request)),
        };
    }

    /**
     * Whether $employee may act on $request's current step right now —
     * either because they are one of the resolved approvers, or because
     * the step's decision was forwarded to them within the last 30 days
     * (see ApprovalService::forward()).
     */
    public function isAuthorized(RequestModel $request, Employee $employee): bool
    {
        $step = $request->currentStep;

        if ($step === null) {
            return false;
        }

        if ($this->resolve($step, $request)->contains(fn (Employee $candidate) => $candidate->id === $employee->id)) {
            return true;
        }

        return $request->approvals()
            ->where('workflow_step_id', $step->id)
            ->where('action', ApprovalAction::Forwarded)
            ->where('forwarded_to_id', $employee->id)
            ->where('decided_at', '>=', Carbon::now()->subDays(self::FORWARD_WINDOW_DAYS))
            ->exists();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function wrap(?Employee $employee): Collection
    {
        return $employee === null ? collect() : collect([$employee]);
    }

    /**
     * Roles are optional/late-bound by design (see the M5 spec's note on
     * the "finance" role for the advance-request workflow): a
     * specific_role step referencing a role that hasn't been seeded yet
     * simply resolves to no approvers, rather than throwing — the request
     * just sits pending until the role exists and someone holds it.
     *
     * @return Collection<int, Employee>
     */
    private function resolveByRole(?string $roleName): Collection
    {
        if (blank($roleName) || ! Role::query()->where('name', $roleName)->exists()) {
            return collect();
        }

        return User::role($roleName)->get()
            ->map(fn (User $user) => $user->employee)
            ->filter()
            ->values();
    }

    private function resolveFormFieldEmployee(WorkflowStep $step, RequestModel $request): ?Employee
    {
        $employeeId = $request->form_data[$step->approver_ref] ?? null;

        return $employeeId === null ? null : Employee::query()->find($employeeId);
    }
}
