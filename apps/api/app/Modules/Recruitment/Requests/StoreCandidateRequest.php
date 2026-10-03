<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\CandidateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'min:2', 'max:200'],
            // Either email OR phone must be present — the paired rule
            // below enforces that at the top level.
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'status' => ['nullable', Rule::in(array_column(CandidateStatus::cases(), 'value'))],
            'source' => ['nullable', 'string', 'max:50'],
            'source_reference' => ['nullable', 'string', 'max:100'],
            'headline' => ['nullable', 'string', 'max:200'],
            'years_of_experience' => ['nullable', 'integer', 'between:0,60'],
            'current_title' => ['nullable', 'string', 'max:150'],
            'current_company' => ['nullable', 'string', 'max:150'],
            'expected_salary_min' => ['nullable', 'numeric', 'min:0'],
            'expected_salary_max' => ['nullable', 'numeric', 'min:0', 'gte:expected_salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'availability' => ['nullable', Rule::in(['immediate', '2_weeks', '1_month', 'negotiable'])],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:60'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            if (empty($this->input('email')) && empty($this->input('phone'))) {
                // Mirrors the CSV import rule — a candidate record with
                // neither email nor phone can never be contacted and
                // can't be deduped on future imports.
                $v->errors()->add('email', 'يجب إدخال البريد الإلكتروني أو رقم الهاتف على الأقل.');
            }
        });
    }
}
