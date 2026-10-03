<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Candidate
 */
class CandidateResource extends JsonResource
{
    /**
     * Signed-URL lifetime for the private resume — mirrors
     * EmployeeResource / LeaveRequestResource so refetch cadence is
     * consistent across the admin surfaces.
     */
    private const RESUME_LINK_LIFETIME_MINUTES = 30;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'candidate_number' => $this->candidate_number,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'city' => $this->city,
            'linkedin_url' => $this->linkedin_url,
            'portfolio_url' => $this->portfolio_url,
            'status' => $this->status?->value,
            'source' => $this->source,
            'source_reference' => $this->source_reference,
            'headline' => $this->headline,
            'years_of_experience' => $this->years_of_experience,
            'current_title' => $this->current_title,
            'current_company' => $this->current_company,
            'expected_salary_min' => $this->expected_salary_min !== null ? (float) $this->expected_salary_min : null,
            'expected_salary_max' => $this->expected_salary_max !== null ? (float) $this->expected_salary_max : null,
            'salary_currency' => $this->salary_currency,
            'availability' => $this->availability,
            'skills' => $this->skills,
            'languages' => $this->languages,
            'notes' => $this->notes,
            // Only PRESENCE of the resume leaks to the client — the
            // URL below is a short-lived signed link that reveals the
            // storage path only in-flight.
            'has_resume' => $this->resume_path !== null,
            'resume_url' => $this->resume_path
                ? URL::temporarySignedRoute(
                    'candidates.resume.download',
                    now()->addMinutes(self::RESUME_LINK_LIFETIME_MINUTES),
                    ['candidate' => $this->id],
                )
                : null,
            'resume_uploaded_at' => $this->resume_uploaded_at?->toIso8601String(),
            'created_by' => $this->whenLoaded('createdByUser', fn () => $this->createdByUser === null ? null : [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
