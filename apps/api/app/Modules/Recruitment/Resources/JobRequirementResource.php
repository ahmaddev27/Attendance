<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\JobRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobRequirement
 */
class JobRequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_number' => $this->job_number,

            'recruitment_case_id' => $this->recruitment_case_id,
            'recruitment_case' => $this->whenLoaded('recruitmentCase', fn () => $this->recruitmentCase === null ? null : [
                'id' => $this->recruitmentCase->id,
                'case_number' => $this->recruitmentCase->case_number,
                'title' => $this->recruitmentCase->title,
                'client' => $this->recruitmentCase->relationLoaded('client') && $this->recruitmentCase->client !== null
                    ? (new ClientSummaryResource($this->recruitmentCase->client))->toArray($request)
                    : null,
            ]),

            'pipeline_id' => $this->pipeline_id,
            'pipeline' => $this->whenLoaded('pipeline', fn () => $this->pipeline === null ? null : [
                'id' => $this->pipeline->id,
                'code' => $this->pipeline->code,
                'name' => $this->pipeline->name,
            ]),

            'current_stage_id' => $this->current_stage_id,
            'current_stage' => $this->whenLoaded('currentStage', fn () => $this->currentStage === null ? null : (new RecruitmentPipelineStageResource($this->currentStage))->toArray($request)),

            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),

            'title' => $this->title,
            'department' => $this->department,
            'openings' => $this->openings,
            'employment_type' => $this->employment_type,
            'work_mode' => $this->work_mode,
            'location' => $this->location,

            'salary_min' => $this->salary_min !== null ? (float) $this->salary_min : null,
            'salary_max' => $this->salary_max !== null ? (float) $this->salary_max : null,
            'salary_currency' => $this->salary_currency,

            'required_experience_years' => $this->required_experience_years,
            'education_level' => $this->education_level,
            'required_skills' => $this->required_skills,
            'nice_to_have_skills' => $this->nice_to_have_skills,
            'required_languages' => $this->required_languages,

            'description' => $this->description,
            'responsibilities' => $this->responsibilities,

            'publication_url' => $this->publication_url,
            'published_at' => $this->published_at?->toIso8601String(),
            'application_deadline' => $this->application_deadline?->toDateString(),
            'target_start_date' => $this->target_start_date?->toDateString(),

            'status' => $this->status?->value,
            'stage_entered_at' => $this->stage_entered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),

            // Set when the job was pulled from an outside board (BrightGaza).
            'external' => $this->external_source === null ? null : [
                'source' => $this->external_source,
                'id' => $this->external_id,
                'status' => $this->external_status,
                'synced_at' => $this->external_synced_at?->toIso8601String(),
                'details' => $this->externalDetails(),
            ],

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The board fields TAQAT has no column for, flattened for display. The
     * complete payload stays in the database.
     *
     * @return array<string, mixed>
     */
    private function externalDetails(): array
    {
        $payload = is_array($this->external_payload) ? $this->external_payload : [];

        return [
            'category' => $payload['category']['name'] ?? null,
            'sub_category' => $payload['sub_category']['name'] ?? null,
            'job_type' => $payload['type']['label'] ?? null,
            'contract_time_type' => $payload['contract_time_type'] ?? null,
            'weekly_hours' => $payload['weekly_hours'] ?? null,
            'experience_level' => $payload['experience_level'] ?? null,
            'duration' => $payload['duration'] ?? null,
            'proposal_count' => $payload['proposal_count'] ?? $payload['proposals'] ?? null,
            'last_proposal_at' => $payload['last_proposal_time'] ?? null,
            'posted_at' => $payload['created_at'] ?? null,
            'poster' => $payload['client']['name'] ?? null,
            'poster_country' => $payload['client']['country']['name'] ?? null,
            'employer_type' => $payload['employer_type']['name'] ?? null,
            'is_open' => $payload['is_open'] ?? null,
        ];
    }
}
