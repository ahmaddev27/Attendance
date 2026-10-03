<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape-check only — the per-field type/options validation lives in
 * CandidateScreeningService::validateAgainstSchema() because the rules
 * are derived from the stage's `screening_schema` JSON, not static.
 */
class StoreCandidateScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('screen-candidates') === true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scorecard' => ['required', 'array'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
