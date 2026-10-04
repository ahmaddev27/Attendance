<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Resources;

use App\Models\Attendance;
use App\Shared\Support\IpMatcher;
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
            // Owner's 2026-10-04 ask: surface the IP that recorded the
            // scan so admins can see where a punch came from without
            // opening the row — same column already powers the `origin`
            // badge (onsite/remote), this is the raw value.
            'check_in_ip' => $this->check_in_ip,
            'check_out_ip' => $this->check_out_ip,
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
            'origin' => $this->resolveOrigin(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Display-only badge, decoupled from enforce_ip: even when the device
     * is NOT enforcing an IP whitelist, we still surface whether the
     * check-in came from inside or outside that whitelist so admins can
     * spot remote punches at a glance.
     *
     * - onsite:  check_in_ip matches at least one whitelist entry.
     * - remote:  check_in_ip is set, whitelist is non-empty, no match.
     * - unknown: check-in device missing OR whitelist empty OR IP missing.
     */
    private function resolveOrigin(): string
    {
        $ip = $this->check_in_ip;
        $device = $this->checkInDevice;

        if ($ip === null || $device === null) {
            return 'unknown';
        }

        $whitelist = $device->ip_whitelist;

        if (empty($whitelist)) {
            return 'unknown';
        }

        return IpMatcher::matchesAny($ip, $whitelist) ? 'onsite' : 'remote';
    }
}
