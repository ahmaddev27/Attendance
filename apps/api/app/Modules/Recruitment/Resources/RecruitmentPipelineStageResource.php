<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\RecruitmentPipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecruitmentPipelineStage
 */
class RecruitmentPipelineStageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'display_order' => $this->display_order,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,

            'owner_rule_type' => $this->owner_rule_type?->value,
            'owner_rule_value' => $this->owner_rule_value,

            'sla_hours' => $this->sla_hours,
            'auto_generate_task' => (bool) $this->auto_generate_task,
            'task_title_template' => $this->task_title_template,
            'task_priority' => $this->task_priority,

            'requires_fields' => $this->requiredFields(),
            'is_terminal' => (bool) $this->is_terminal,
        ];
    }
}
