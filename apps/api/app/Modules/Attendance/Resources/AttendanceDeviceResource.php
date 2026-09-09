<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Resources;

use App\Models\AttendanceDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceDevice
 */
class AttendanceDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'qr_token' => $this->qr_token,
            'qr_rotates_every_seconds' => $this->qr_rotates_every_seconds,
            'last_token_rotated_at' => $this->last_token_rotated_at?->toIso8601String(),
            'is_token_expired' => $this->isTokenExpired(),
            'allowed_lat' => $this->allowed_lat !== null ? (float) $this->allowed_lat : null,
            'allowed_lng' => $this->allowed_lng !== null ? (float) $this->allowed_lng : null,
            'allowed_radius_meters' => $this->allowed_radius_meters,
            'ip_whitelist' => $this->ip_whitelist,
            'enforce_geo' => (bool) $this->enforce_geo,
            'enforce_ip' => (bool) $this->enforce_ip,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
