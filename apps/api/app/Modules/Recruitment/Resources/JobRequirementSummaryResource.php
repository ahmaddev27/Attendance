<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\JobRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobRequirement
 */
class JobRequirementSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_number' => $this->job_number,
            'title' => $this->title,
            'status' => $this->status?->value,
            'openings' => $this->openings,
            'work_mode' => $this->work_mode,
            'employment_type' => $this->employment_type,
            'current_stage_id' => $this->current_stage_id,
            'current_stage' => $this->whenLoaded('currentStage', fn () => $this->currentStage === null ? null : [
                'id' => $this->currentStage->id,
                'code' => $this->currentStage->code,
                'name' => $this->currentStage->name,
                'is_terminal' => (bool) $this->currentStage->is_terminal,
            ]),
        ];
    }
}
