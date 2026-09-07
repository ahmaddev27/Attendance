<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\ApprovalAction;
use Database\Factories\RequestApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single immutable decision on a Request's current step. Only
 * `created_at` is tracked (no `updated_at`) — an approval record is
 * written once and never edited; correcting a mistaken decision means
 * recording a new one, not mutating history.
 */
class RequestApproval extends Model
{
    /** @use HasFactory<RequestApprovalFactory> */
    use HasFactory;

    /**
     * @var string|null
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'request_id',
        'workflow_step_id',
        'approver_id',
        'action',
        'comment',
        'forwarded_to_id',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * @return BelongsTo<WorkflowStep, $this>
     */
    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function forwardedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'forwarded_to_id');
    }
}
