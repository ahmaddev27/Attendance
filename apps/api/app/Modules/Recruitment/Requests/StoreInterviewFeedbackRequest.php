<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\InterviewRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Scorecard shape-check only — per-field validation against the stage
 * schema lives in the service, mirroring StoreCandidateScreeningRequest.
 */
class StoreInterviewFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit-interview-feedback') === true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scorecard' => ['required', 'array'],
            'recommendation' => [
                'required',
                Rule::in(array_map(fn (InterviewRecommendation $r) => $r->value, InterviewRecommendation::cases())),
            ],
            'strengths' => ['nullable', 'string', 'max:4000'],
            'weaknesses' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
