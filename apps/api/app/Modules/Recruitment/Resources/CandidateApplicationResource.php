<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\CandidateApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateApplication
 */
class CandidateApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'candidate' => $this->whenLoaded('candidate', fn () => $this->candidate === null ? null : [
                'id' => $this->candidate->id,
                'candidate_number' => $this->candidate->candidate_number,
                'full_name' => $this->candidate->full_name,
                'email' => $this->candidate->email,
                'phone' => $this->candidate->phone,
                'headline' => $this->candidate->headline,
            ]),
            'job' => $this->whenLoaded('jobRequirement', fn () => $this->jobRequirement === null ? null : [
                'id' => $this->jobRequirement->id,
                'job_number' => $this->jobRequirement->job_number,
                'title' => $this->jobRequirement->title,
            ]),
            'current_stage' => $this->whenLoaded('currentStage', fn () => $this->currentStage === null ? null : [
                'id' => $this->currentStage->id,
                'code' => $this->currentStage->code,
                'name' => $this->currentStage->name,
                'display_order' => $this->currentStage->display_order,
            ]),
            'status' => $this->status?->value,
            'source' => $this->source,
            'applied_at' => $this->applied_at?->toIso8601String(),
            'is_shortlisted' => (bool) $this->is_shortlisted,
            'shortlisted_at' => $this->shortlisted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'rejection_stage_code' => $this->rejection_stage_code,
            'notes' => $this->notes,
            'stage_entered_at' => $this->stage_entered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
