<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk reorder: an ordered list of stage ids belonging to the pipeline
 * on the route. The service replays them into display_order starting at
 * 1 in a single transaction, side-stepping the (pipeline_id,
 * display_order) uniqueness constraint by using negative sentinels
 * during the shuffle.
 */
class ReorderPipelineStagesRequest extends FormRequest
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
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['integer', 'distinct', 'exists:recruitment_pipeline_stages,id'],
        ];
    }
}
