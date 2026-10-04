<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Interview
 *
 * `average_score` is computed on the fly from the loaded feedbacks
 * rather than passed in — the frontend never asks for an interview
 * without its feedback list, so the extra arithmetic is cheap and the
 * contract stays one shape.
 */
class InterviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'interview_number' => $this->interview_number,
            'application_id' => $this->application_id,
            'application' => $this->whenLoaded('application', fn () => $this->application === null ? null : [
                'id' => $this->application->id,
                'application_number' => $this->application->application_number,
                'candidate_name' => $this->application->candidate?->full_name,
                'job_title' => $this->application->jobRequirement?->title,
            ]),
            'kind' => $this->kind?->value,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'duration_minutes' => (int) $this->duration_minutes,
            'timezone' => $this->timezone,
            'location' => $this->location,
            'meeting_url' => $this->meeting_url,
            'meeting_notes' => $this->meeting_notes,
            'status' => $this->status?->value,
            'cancelled_reason' => $this->cancelled_reason,
            'rescheduled_from_id' => $this->rescheduled_from_id,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy === null ? null : [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'feedbacks' => $this->whenLoaded(
                'feedbacks',
                fn () => InterviewFeedbackResource::collection($this->feedbacks),
            ),
            'average_score' => $this->averageScore(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Mean of the overall_score across submitted feedbacks. Returns
     * null when no feedback carries a numeric score — the UI uses that
     * to show "— / 5" instead of a misleading 0.
     */
    private function averageScore(): ?float
    {
        if (! $this->relationLoaded('feedbacks')) {
            return null;
        }

        $scores = $this->feedbacks
            ->pluck('overall_score')
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => (float) $v);

        if ($scores->isEmpty()) {
            return null;
        }

        return round($scores->sum() / $scores->count(), 2);
    }
}
