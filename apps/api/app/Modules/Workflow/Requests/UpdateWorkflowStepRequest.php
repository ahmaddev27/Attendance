<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Requests;

use App\Shared\Enums\ApproverType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * step_order is intentionally not accepted here — reordering is a
 * dedicated, all-or-nothing operation (see ReorderStepsRequest /
 * WorkflowService::reorderSteps()) so the unique(workflow_id, step_order)
 * index is never at risk from a partial update.
 */
class UpdateWorkflowStepRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:150'],
            'approver_type' => ['sometimes', Rule::enum(ApproverType::class)],
            'approver_ref' => [
                'nullable',
                'string',
                'max:100',
                'required_if:approver_type,'.ApproverType::SpecificEmployee->value.','.ApproverType::SpecificRole->value.','.ApproverType::FormField->value,
            ],
            'can_reject' => ['sometimes', 'boolean'],
            'can_return' => ['sometimes', 'boolean'],
            'can_forward' => ['sometimes', 'boolean'],
            'sla_hours' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
