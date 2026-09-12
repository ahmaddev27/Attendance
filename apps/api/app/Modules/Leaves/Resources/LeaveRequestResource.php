<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Resources;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * How long a signed download URL for the attachment stays valid.
     * Matches TaskAttachmentService::DOWNLOAD_LINK_LIFETIME_MINUTES so
     * the two attachment surfaces behave the same from the client's
     * perspective.
     */
    private const ATTACHMENT_LINK_LIFETIME_MINUTES = 30;

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
            // Attachments live on the private `local` disk (see
            // LeaveAttachmentService). Never leak a public URL — the FE
            // downloads via a short-lived signed route whose middleware
            // gate authorizes the caller (owner or HR) inside the
            // controller action.
            'attachment_url' => $this->attachment_path
                ? URL::temporarySignedRoute(
                    'leaves.attachment.download',
                    now()->addMinutes(self::ATTACHMENT_LINK_LIFETIME_MINUTES),
                    ['leaveRequest' => $this->id],
                )
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
