<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Resources;

use App\Models\WorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkflowStep
 */
class WorkflowStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'step_order' => $this->step_order,
            'name' => $this->name,
            'approver_type' => $this->approver_type?->value,
            'approver_ref' => $this->approver_ref,
            'can_reject' => (bool) $this->can_reject,
            'can_return' => (bool) $this->can_return,
            'can_forward' => (bool) $this->can_forward,
            'sla_hours' => $this->sla_hours,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
