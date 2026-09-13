<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\JobRequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Same shape as StoreJobRequirementRequest with everything as `sometimes`,
 * plus the stage-related fields the advance-stage endpoint may set
 * (publication_url when leaving the "publish" stage, etc.).
 * pipeline_id / current_stage_id are NOT editable here — those change
 * only through the dedicated advance-stage endpoint (JobRequirementService).
 */
class UpdateJobRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'string', 'max:200'],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'openings' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'employment_type' => ['sometimes', 'string', 'in:full_time,part_time,contract,intern,temporary'],
            'work_mode' => ['sometimes', 'string', 'in:remote,onsite,hybrid'],
            'location' => ['sometimes', 'nullable', 'string', 'max:200'],

            'salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'salary_currency' => ['sometimes', 'nullable', 'string', 'size:3'],

            'required_experience_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99'],
            'education_level' => ['sometimes', 'nullable', 'string', 'max:50'],
            'required_skills' => ['sometimes', 'nullable', 'array'],
            'required_skills.*' => ['string', 'max:100'],
            'nice_to_have_skills' => ['sometimes', 'nullable', 'array'],
            'nice_to_have_skills.*' => ['string', 'max:100'],
            'required_languages' => ['sometimes', 'nullable', 'array'],
            'required_languages.*' => ['string', 'max:50'],

            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'responsibilities' => ['sometimes', 'nullable', 'string', 'max:10000'],

            'publication_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url'],
            'application_deadline' => ['sometimes', 'nullable', 'date'],
            'target_start_date' => ['sometimes', 'nullable', 'date'],

            'status' => ['sometimes', 'string', Rule::in(array_map(fn (JobRequirementStatus $c) => $c->value, JobRequirementStatus::cases()))],
        ];
    }
}
