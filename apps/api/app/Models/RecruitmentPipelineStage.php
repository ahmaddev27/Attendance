<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\StageOwnerRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stage inside a RecruitmentPipeline. Carries the rules the
 * PipelineTaskGenerator uses to (a) figure out who owns the next
 * task, (b) whether to auto-create it at all, and (c) what fields on
 * the JobRequirement must be non-null before a transition out is
 * allowed. `requires_fields` is JSON so the admin can add new gates
 * without a migration.
 */
class RecruitmentPipelineStage extends Model
{
    protected $fillable = [
        'pipeline_id',
        'display_order',
        'code',
        'name',
        'description',
        'owner_rule_type',
        'owner_rule_value',
        'sla_hours',
        'auto_generate_task',
        'task_title_template',
        'task_priority',
        'requires_fields',
        'is_terminal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'sla_hours' => 'integer',
            'auto_generate_task' => 'boolean',
            'is_terminal' => 'boolean',
            'requires_fields' => 'array',
            'owner_rule_type' => StageOwnerRule::class,
        ];
    }

    /**
     * @return BelongsTo<RecruitmentPipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(RecruitmentPipeline::class, 'pipeline_id');
    }

    /**
     * @return list<string>
     */
    public function requiredFields(): array
    {
        $value = $this->requires_fields;

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
