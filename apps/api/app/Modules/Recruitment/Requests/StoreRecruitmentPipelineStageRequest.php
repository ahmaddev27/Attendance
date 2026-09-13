<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use App\Shared\Enums\StageOwnerRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecruitmentPipelineStageRequest extends FormRequest
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
        $pipelineId = $this->route('pipeline');
        $pipelineId = is_object($pipelineId) && method_exists($pipelineId, 'getKey')
            ? $pipelineId->getKey()
            : (int) $pipelineId;

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('recruitment_pipeline_stages', 'code')->where('pipeline_id', $pipelineId)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['sometimes', 'integer', 'min:1', 'max:32767'],

            'owner_rule_type' => ['required', 'string', Rule::in(array_map(fn (StageOwnerRule $r) => $r->value, StageOwnerRule::cases()))],
            'owner_rule_value' => ['nullable', 'string', 'max:100'],

            'sla_hours' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'auto_generate_task' => ['sometimes', 'boolean'],
            'task_title_template' => ['nullable', 'string', 'max:200'],
            'task_priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],

            'requires_fields' => ['nullable', 'array'],
            'requires_fields.*' => ['string', 'max:100'],
            'is_terminal' => ['sometimes', 'boolean'],
        ];
    }
}
