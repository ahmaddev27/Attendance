<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /jobs/{job}/advance-stage. When target_stage_id is omitted the
 * service defaults to the "next" stage in display_order — the common
 * happy path. When provided, it must be a stage in the same pipeline
 * and the service will verify it's reachable (never backwards, never
 * skipping a terminal stage).
 *
 * `fields` collects the free-form patches expected by the target
 * stage's requires_fields list — publication_url on publish, etc. The
 * service validates the payload matches the stage's requirements
 * before advancing.
 */
class AdvanceJobStageRequest extends FormRequest
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
            'target_stage_id' => ['sometimes', 'integer', 'exists:recruitment_pipeline_stages,id'],
            'fields' => ['sometimes', 'array'],
            'fields.publication_url' => ['sometimes', 'string', 'max:500', 'url'],
            'fields.shortlist_ids' => ['sometimes', 'array'],
            'fields.shortlist_ids.*' => ['integer'],
            'fields.contract_terms' => ['sometimes', 'string', 'max:10000'],
            // Optional handoff note that becomes the task description on
            // the newly generated task for the next stage owner.
            'handoff_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
