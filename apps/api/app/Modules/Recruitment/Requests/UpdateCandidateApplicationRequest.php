<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\CandidateApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Patches a CandidateApplication. The STAGE field is deliberately NOT
 * writable here — stage transitions go through
 * CandidateApplicationService::advanceStage() so the pipeline engine
 * fires the right events and tasks.
 */
class UpdateCandidateApplicationRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(array_column(CandidateApplicationStatus::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
