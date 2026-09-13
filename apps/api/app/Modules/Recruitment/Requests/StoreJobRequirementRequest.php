<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\JobRequirementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequirementRequest extends FormRequest
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
            'recruitment_case_id' => ['required', 'integer', 'exists:recruitment_cases,id'],
            // pipeline_id defaults to the seeded 'standard' pipeline in
            // the service when omitted — nullable at the request layer.
            'pipeline_id' => ['nullable', 'integer', 'exists:recruitment_pipelines,id'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],

            'title' => ['required', 'string', 'max:200'],
            'department' => ['nullable', 'string', 'max:100'],
            'openings' => ['required', 'integer', 'min:1', 'max:65535'],
            'employment_type' => ['required', 'string', 'in:full_time,part_time,contract,intern,temporary'],
            'work_mode' => ['required', 'string', 'in:remote,onsite,hybrid'],
            'location' => ['nullable', 'string', 'max:200'],

            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3'],

            'required_experience_years' => ['nullable', 'integer', 'min:0', 'max:99'],
            'education_level' => ['nullable', 'string', 'max:50'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:100'],
            'nice_to_have_skills' => ['nullable', 'array'],
            'nice_to_have_skills.*' => ['string', 'max:100'],
            'required_languages' => ['nullable', 'array'],
            'required_languages.*' => ['string', 'max:50'],

            'description' => ['nullable', 'string', 'max:10000'],
            'responsibilities' => ['nullable', 'string', 'max:10000'],

            'application_deadline' => ['nullable', 'date'],
            'target_start_date' => ['nullable', 'date'],

            'status' => ['sometimes', 'string', Rule::in(array_map(fn (JobRequirementStatus $c) => $c->value, JobRequirementStatus::cases()))],
        ];
    }
}
