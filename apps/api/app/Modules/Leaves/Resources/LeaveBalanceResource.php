<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Resources;

use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveBalance
 */
class LeaveBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'year' => $this->year,
            'entitlement' => (float) $this->entitlement,
            'used' => (float) $this->used,
            'pending' => (float) $this->pending,
            'carry_over_from_previous' => (float) $this->carry_over_from_previous,
            'remaining' => $this->remaining,
            // json_encode() cannot represent INF (unlimited, for an
            // allow_negative_balance type) — null stands in for "no cap".
            'available' => is_infinite($this->available) ? null : $this->available,
            'leave_type' => $this->whenLoaded('leaveType', fn () => $this->leaveType === null ? null : [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
                'color' => $this->leaveType->color,
                'is_balance_based' => $this->leaveType->is_balance_based,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
