<?php

namespace App\Services;

use App\DataObjects\FraudCheckContext;
use App\DataObjects\FraudCheckResult;
use App\Enums\FraudCheckStatus;

class FraudGuardService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function check(FraudCheckContext $ctx): FraudCheckResult
    {
        $gpsEnabled = (bool) $this->settings->get('gps_enabled', false);
        $ipEnabled = (bool) $this->settings->get('ip_enabled', false);

        if (! $gpsEnabled && ! $ipEnabled) {
            return new FraudCheckResult(true, FraudCheckStatus::Skipped);
        }

        $gpsPassed = $gpsEnabled ? $this->gpsPassed($ctx) : null;
        $ipPassed = $ipEnabled ? $this->ipPassed($ctx) : null;

        $enabledChecks = array_filter([$gpsPassed, $ipPassed], fn ($v) => $v !== null);
        $passed = in_array(true, $enabledChecks, true);

        if ($passed) {
            return new FraudCheckResult(true, FraudCheckStatus::Passed);
        }

        $failure = $gpsEnabled && $gpsPassed === false
            ? FraudCheckStatus::GpsFailed
            : FraudCheckStatus::IpFailed;

        return new FraudCheckResult(false, $failure);
    }

    private function gpsPassed(FraudCheckContext $ctx): bool
    {
        if ($ctx->latitude === null || $ctx->longitude === null) {
            return false;
        }

        $lat = (float) $this->settings->get('office_lat');
        $lng = (float) $this->settings->get('office_lng');
        $radius = (float) $this->settings->get('geofence_radius_meters', 100);

        if ($lat === 0.0 && $lng === 0.0) {
            return false;
        }

        $distance = $this->haversineMeters($ctx->latitude, $ctx->longitude, $lat, $lng);

        return $distance <= $radius;
    }

    private function ipPassed(FraudCheckContext $ctx): bool
    {
        $whitelist = (array) $this->settings->get('ip_whitelist', []);

        return in_array($ctx->ip, $whitelist, true);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
