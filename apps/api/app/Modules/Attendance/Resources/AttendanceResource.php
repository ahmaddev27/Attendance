<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Resources;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
                'avatar_url' => $this->employee->avatar_url,
            ]),
            'date' => $this->date?->toDateString(),
            'check_in_at' => $this->check_in_at?->toIso8601String(),
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'check_in_device' => $this->whenLoaded(
                'checkInDevice',
                fn () => $this->checkInDevice ? new AttendanceDeviceResource($this->checkInDevice) : null,
            ),
            'check_out_device' => $this->whenLoaded(
                'checkOutDevice',
                fn () => $this->checkOutDevice ? new AttendanceDeviceResource($this->checkOutDevice) : null,
            ),
            'total_minutes' => $this->total_minutes,
            'total_hours' => $this->total_hours,
            'late_minutes' => $this->late_minutes,
            'early_leave_minutes' => $this->early_leave_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
