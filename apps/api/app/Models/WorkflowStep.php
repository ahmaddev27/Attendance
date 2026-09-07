<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\ApproverType;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'step_order',
        'name',
        'approver_type',
        'approver_ref',
        'can_reject',
        'can_return',
        'can_forward',
        'sla_hours',
    ];

    /**
     * Mirrors the `can_reject`/`can_return`/`can_forward` columns' DB
     * defaults so a freshly created, in-memory model (before any explicit
     * reload) already reflects them — Eloquent does not otherwise know
     * about column defaults it didn't set.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'can_reject' => true,
        'can_return' => false,
        'can_forward' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'approver_type' => ApproverType::class,
            'can_reject' => 'boolean',
            'can_return' => 'boolean',
            'can_forward' => 'boolean',
            'sla_hours' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
