<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Resources;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveType
 */
class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_paid' => $this->is_paid,
            'is_balance_based' => $this->is_balance_based,
            'default_annual_entitlement' => (float) $this->default_annual_entitlement,
            'allow_negative_balance' => $this->allow_negative_balance,
            'requires_attachment' => $this->requires_attachment,
            'max_consecutive_days' => $this->max_consecutive_days,
            'min_notice_days' => $this->min_notice_days,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
