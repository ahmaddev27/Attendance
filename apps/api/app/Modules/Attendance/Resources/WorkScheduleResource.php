<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Resources;

use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkSchedule
 */
class WorkScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'check_in_time' => $this->check_in_time?->format('H:i'),
            'check_out_time' => $this->check_out_time?->format('H:i'),
            'min_hours_per_day' => (float) $this->min_hours_per_day,
            'grace_late_minutes' => $this->grace_late_minutes,
            'grace_early_leave_minutes' => $this->grace_early_leave_minutes,
            'workdays' => $this->workdays,
            'is_flexible' => $this->is_flexible,
            'is_active' => $this->is_active,
            'expected_minutes' => $this->expectedMinutes(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
