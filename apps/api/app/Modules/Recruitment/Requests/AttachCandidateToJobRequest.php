<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Links an existing Candidate (by id) to the Job in the route. Caller
 * can also inline-create a candidate by posting the full Candidate
 * payload instead — the controller branches on which path is sent.
 */
class AttachCandidateToJobRequest extends FormRequest
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
            'candidate_id' => ['required', 'integer', Rule::exists('candidates', 'id')->whereNull('deleted_at')],
            'source' => ['nullable', Rule::in(['manual', 'csv_import', 'brightgaza'])],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
