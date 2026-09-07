<?php

declare(strict_types=1);

namespace App\Modules\Requests\Resources;

use App\Models\RequestApproval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestApproval
 */
class ApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'workflow_step_id' => $this->workflow_step_id,
            'workflow_step' => $this->whenLoaded('workflowStep', fn () => $this->workflowStep === null ? null : [
                'id' => $this->workflowStep->id,
                'name' => $this->workflowStep->name,
                'step_order' => $this->workflowStep->step_order,
            ]),
            'approver_id' => $this->approver_id,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver === null ? null : [
                'id' => $this->approver->id,
                'full_name' => $this->approver->full_name,
            ]),
            'action' => $this->action?->value,
            'comment' => $this->comment,
            'forwarded_to_id' => $this->forwarded_to_id,
            'forwarded_to' => $this->whenLoaded('forwardedTo', fn () => $this->forwardedTo === null ? null : [
                'id' => $this->forwardedTo->id,
                'full_name' => $this->forwardedTo->full_name,
            ]),
            'decided_at' => $this->decided_at?->toIso8601String(),
        ];
    }
}
