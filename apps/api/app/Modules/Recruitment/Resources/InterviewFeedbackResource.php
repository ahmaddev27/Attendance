<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\InterviewFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InterviewFeedback
 */
class InterviewFeedbackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'interview_id' => $this->interview_id,
            'interviewer' => $this->whenLoaded('interviewer', fn () => $this->interviewer === null ? null : [
                'id' => $this->interviewer->id,
                'name' => $this->interviewer->name,
            ]),
            'scorecard' => $this->scorecard,
            'overall_score' => $this->overall_score !== null ? (float) $this->overall_score : null,
            'recommendation' => $this->recommendation?->value,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'notes' => $this->notes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
