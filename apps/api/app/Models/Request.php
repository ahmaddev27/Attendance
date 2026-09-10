<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\ApprovalAction;
use App\Shared\Enums\ApproverType;
use App\Shared\Enums\RequestStatus;
use Database\Factories\RequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * A single submitted instance of a RequestType, routed through that
 * type's Workflow one step at a time via `current_step_id`.
 *
 * Named `Request` to match the ERD/spec exactly. This collides with
 * `Illuminate\Http\Request`, so every consumer outside this namespace
 * must import it explicitly as `use App\Models\Request as RequestModel;`
 * (or any other alias) rather than a bare `use App\Models\Request;`
 * alongside the HTTP one.
 */
class Request extends Model
{
    /** @use HasFactory<RequestFactory> */
    use HasFactory, Searchable;

    /**
     * How long after a `forwarded` approval action the forwarded-to
     * employee remains an alternate approver for that step — see
     * scopePendingForApprover() and
     * App\Modules\Requests\Services\ApproverResolver::isAuthorized().
     */
    private const FORWARD_WINDOW_DAYS = 30;

    protected $fillable = [
        'request_number',
        'employee_id',
        'request_type_id',
        'form_data',
        'status',
        'current_step_id',
        'submitted_at',
        'completed_at',
    ];

    /**
     * Mirrors the `status` column's DB default so a freshly created,
     * in-memory model (before any explicit reload) already reflects it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'form_data' => 'array',
            'status' => RequestStatus::class,
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<RequestType, $this>
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    /**
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    /**
     * @return HasMany<RequestApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class);
    }

    /**
     * The single most recent decision on this request — a convenience
     * relation so RequestResource can show it without loading the full
     * approvals history.
     *
     * @return HasOne<RequestApproval, $this>
     */
    public function latestApproval(): HasOne
    {
        return $this->hasOne(RequestApproval::class)->latestOfMany('decided_at');
    }

    public function getTitleAttribute(): string
    {
        return trim(($this->requestType?->name ?? '').' '.$this->request_number);
    }

    /**
     * @param  Builder<Request>  $query
     * @return Builder<Request>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Pending);
    }

    /**
     * Requests currently awaiting a decision from $employee: either they
     * are the (or *an*) approver the current step's approver_type/ref
     * resolves to, or the current step's decision was forwarded to them
     * within the last 30 days (see ApprovalService::forward()).
     *
     * Every approver_type except `form_field` is resolvable directly in
     * SQL, so those are filtered here for a single, index-friendly query
     * against the whole pending-requests table. `form_field` points at a
     * different, per-row JSON key (`current_step.approver_ref` names
     * which key in *that row's* `form_data` holds the employee id), which
     * cannot be expressed as one portable WHERE clause across both MySQL
     * and SQLite — so that one case is resolved by loading just the
     * (typically small) set of pending, form_field-routed requests and
     * checking each in PHP instead of trying to force it into SQL.
     *
     * @param  Builder<Request>  $query
     * @return Builder<Request>
     */
    public function scopePendingForApprover(Builder $query, Employee $employee): Builder
    {
        // Employee has no direct "its login user" relation worth trusting
        // here (employees.user_id is a separate, largely unused column —
        // every other module in this codebase resolves the link the other
        // way round, via users.employee_id, e.g.
        // EmployeeLeavesController::resolveEmployee()'s
        // $request->user()->employee).
        $roleNames = User::query()->where('employee_id', $employee->id)->first()?->getRoleNames()->all() ?? [];
        $formFieldMatchIds = $this->formFieldMatchIds($employee);

        return $query
            ->where('status', RequestStatus::Pending)
            // Symmetric with the forwarded-to inclusion in the OR block
            // below: once $employee forwards a request on its current
            // step, that request must LEAVE their inbox for as long as
            // the forward remains in force. Without this an approver
            // who hands a request off still sees it — and both they and
            // the forwarded-to employee can act on it.
            ->whereDoesntHave('approvals', function (Builder $approval) use ($employee) {
                $approval->where('action', ApprovalAction::Forwarded)
                    ->where('approver_id', $employee->id)
                    ->where('decided_at', '>=', Carbon::now()->subDays(self::FORWARD_WINDOW_DAYS))
                    ->whereColumn('workflow_step_id', 'requests.current_step_id');
            })
            ->where(function (Builder $q) use ($employee, $roleNames, $formFieldMatchIds) {
                $q->whereHas('currentStep', function (Builder $step) use ($employee) {
                    $step->where('approver_type', ApproverType::SpecificEmployee)
                        ->where('approver_ref', (string) $employee->id);
                });

                $q->orWhere(function (Builder $q) use ($employee) {
                    $q->whereHas('currentStep', fn (Builder $step) => $step->where('approver_type', ApproverType::DirectManager))
                        ->whereHas('employee', fn (Builder $e) => $e->where('direct_manager_id', $employee->id));
                });

                $q->orWhere(function (Builder $q) use ($employee) {
                    $q->whereHas('currentStep', fn (Builder $step) => $step->where('approver_type', ApproverType::DepartmentManager))
                        ->whereHas('employee.department', fn (Builder $d) => $d->where('manager_id', $employee->id));
                });

                if ($roleNames !== []) {
                    $q->orWhereHas('currentStep', function (Builder $step) use ($roleNames) {
                        $step->where('approver_type', ApproverType::SpecificRole)
                            ->whereIn('approver_ref', $roleNames);
                    });
                }

                if ($formFieldMatchIds !== []) {
                    $q->orWhereIn('id', $formFieldMatchIds);
                }

                $q->orWhereHas('approvals', function (Builder $approval) use ($employee) {
                    $approval->where('action', ApprovalAction::Forwarded)
                        ->where('forwarded_to_id', $employee->id)
                        ->where('decided_at', '>=', Carbon::now()->subDays(self::FORWARD_WINDOW_DAYS))
                        ->whereColumn('workflow_step_id', 'requests.current_step_id');
                });
            });
    }

    /**
     * Whether the owning employee may still cancel this request
     * themselves — anything not yet decided, plus a Returned request that
     * hasn't been resubmitted.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            RequestStatus::Draft,
            RequestStatus::Submitted,
            RequestStatus::Pending,
            RequestStatus::Returned,
        ], true);
    }

    /**
     * `form_data` is a JSON column — flatten it to a searchable string so
     * Meilisearch can match on values inside without needing to know each
     * request type's schema. `id` is stringified per Meilisearch's own
     * primary-key convention.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $formData = $this->form_data;

        return [
            'id' => (string) $this->id,
            'request_number' => (string) $this->request_number,
            'form_data' => is_array($formData) ? json_encode($formData, JSON_UNESCAPED_UNICODE) : (string) $formData,
        ];
    }

    /**
     * @return list<int>
     */
    private function formFieldMatchIds(Employee $employee): array
    {
        return static::query()
            ->where('status', RequestStatus::Pending)
            ->whereHas('currentStep', fn (Builder $step) => $step->where('approver_type', ApproverType::FormField))
            ->with('currentStep')
            ->get()
            ->filter(function (self $request) use ($employee) {
                $fieldKey = $request->currentStep?->approver_ref;

                if ($fieldKey === null) {
                    return false;
                }

                return (string) ($request->form_data[$fieldKey] ?? '') === (string) $employee->id;
            })
            ->pluck('id')
            ->all();
    }
}
