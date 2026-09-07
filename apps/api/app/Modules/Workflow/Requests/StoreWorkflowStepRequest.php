<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Requests;

use App\Shared\Enums\ApproverType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowStepRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'approver_type' => ['required', Rule::enum(ApproverType::class)],
            // Not needed for direct_manager/department_manager, which are
            // derived from the requesting employee's own org placement.
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
            // Optional — appended to the end of the workflow when omitted,
            // see WorkflowService::addStep().
            'step_order' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
