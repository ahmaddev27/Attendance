<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Resources;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee === null ? null : [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
            ]),
            'leave_type_id' => $this->leave_type_id,
            'leave_type' => $this->whenLoaded('leaveType', fn () => $this->leaveType === null ? null : [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
                'color' => $this->leaveType->color,
                'requires_attachment' => $this->leaveType->requires_attachment,
            ]),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => (float) $this->days,
            'reason' => $this->reason,
            // FE reads `attachment_url` on the leave-details dialog; the
            // storage path was leaking as an internal-only key. Resolved
            // to a public URL so the review flow can actually download.
            'attachment_url' => $this->attachment_path
                ? Storage::disk('public')->url($this->attachment_path)
                : null,
            'status' => $this->status?->value,
            'reviewed_by' => $this->reviewed_by,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer === null ? null : [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ]),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
