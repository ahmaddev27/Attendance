<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use App\Modules\Attendance\Services\ScanPinService;

/**
 * Check-in / check-out payload: the shared scan identity plus the optional
 * coordinates FraudGuardService checks against the device's geofence.
 */
class ScanRequest extends ScanIdentityRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(ScanPinService $scanPins): array
    {
        return [
            ...parent::rules($scanPins),
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function latitude(): ?float
    {
        $value = $this->validated('latitude');

        return $value === null ? null : (float) $value;
    }

    public function longitude(): ?float
    {
        $value = $this->validated('longitude');

        return $value === null ? null : (float) $value;
    }
}
