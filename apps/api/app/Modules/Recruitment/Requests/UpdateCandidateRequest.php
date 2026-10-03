<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\CandidateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-candidates') === true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'min:2', 'max:200'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'status' => ['sometimes', Rule::in(array_column(CandidateStatus::cases(), 'value'))],
            'headline' => ['sometimes', 'nullable', 'string', 'max:200'],
            'years_of_experience' => ['sometimes', 'nullable', 'integer', 'between:0,60'],
            'current_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'current_company' => ['sometimes', 'nullable', 'string', 'max:150'],
            'expected_salary_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'expected_salary_max' => ['sometimes', 'nullable', 'numeric', 'min:0', 'gte:expected_salary_min'],
            'salary_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'availability' => ['sometimes', 'nullable', Rule::in(['immediate', '2_weeks', '1_month', 'negotiable'])],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:60'],
            'languages' => ['sometimes', 'nullable', 'array'],
            'languages.*' => ['string', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
