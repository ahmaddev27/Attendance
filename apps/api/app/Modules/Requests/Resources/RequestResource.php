<?php

declare(strict_types=1);

namespace App\Modules\Requests\Resources;

use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestModel
 */
class RequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'title' => $this->title,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee === null ? null : [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
            ]),
            'request_type_id' => $this->request_type_id,
            'request_type' => $this->whenLoaded('requestType', fn () => $this->requestType === null ? null : [
                'id' => $this->requestType->id,
                'name' => $this->requestType->name,
                'code' => $this->requestType->code,
                'icon' => $this->requestType->icon,
                'color' => $this->requestType->color,
            ]),
            'form_data' => $this->form_data,
            'status' => $this->status?->value,
            'current_step_id' => $this->current_step_id,
            'current_step' => $this->whenLoaded('currentStep', fn () => $this->currentStep === null ? null : [
                'id' => $this->currentStep->id,
                'name' => $this->currentStep->name,
                'step_order' => $this->currentStep->step_order,
                'can_reject' => (bool) $this->currentStep->can_reject,
                'can_return' => (bool) $this->currentStep->can_return,
                'can_forward' => (bool) $this->currentStep->can_forward,
                'approver_type' => $this->currentStep->approver_type?->value,
                'approver_ref' => $this->currentStep->approver_ref,
            ]),
            'latest_approval' => $this->whenLoaded(
                'latestApproval',
                fn () => $this->latestApproval === null ? null : new ApprovalResource($this->latestApproval)
            ),
            'approvals' => ApprovalResource::collection($this->whenLoaded('approvals')),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
