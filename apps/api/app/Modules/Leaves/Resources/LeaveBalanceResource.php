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
            // FE (Submit Leave dialog) reads `leave_type_id` directly to
            // key its select options — without this the picker's items
            // all got value="undefined" and Radix threw on duplicates.
            'leave_type_id' => $this->leave_type_id,
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
                // FE toggles a "المرفق مطلوب" hint + form validation on
                // this. Missing before → the hint never showed and the
                // submitter could omit a mandatory doc.
                'requires_attachment' => $this->leaveType->requires_attachment,
                'is_paid' => $this->leaveType->is_paid,
                'min_notice_days' => $this->leaveType->min_notice_days,
                'max_consecutive_days' => $this->leaveType->max_consecutive_days,
                'allow_negative_balance' => $this->leaveType->allow_negative_balance,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
