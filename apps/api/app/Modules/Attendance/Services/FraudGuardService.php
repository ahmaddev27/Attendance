<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\AttendanceDevice;
use App\Modules\Attendance\Exceptions\FraudGuardException;

/**
 * Verifies a scan attempt against the device's configured anti-fraud
 * rules. Both checks are opt-in per device: a device with no geofence
 * configured accepts any location, and one with no IP whitelist accepts
 * any network.
 */
class FraudGuardService
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function assertAllowed(AttendanceDevice $device, ?float $latitude, ?float $longitude, string $ip): void
    {
        $this->assertWithinGeofence($device, $latitude, $longitude);
        $this->assertIpAllowed($device, $ip);
    }

    private function assertWithinGeofence(AttendanceDevice $device, ?float $latitude, ?float $longitude): void
    {
        if ($device->allowed_lat === null || $device->allowed_lng === null || ! $device->allowed_radius_meters) {
            return;
        }

        if ($latitude === null || $longitude === null) {
            throw new FraudGuardException('Location is required to check in on this device.');
        }

        $distanceMeters = $this->haversineDistanceMeters(
            (float) $device->allowed_lat,
            (float) $device->allowed_lng,
            $latitude,
            $longitude,
        );

        if ($distanceMeters > $device->allowed_radius_meters) {
            throw new FraudGuardException('You are outside the allowed check-in area for this device.');
        }
    }

    private function assertIpAllowed(AttendanceDevice $device, string $ip): void
    {
        $whitelist = $device->ip_whitelist;

        if (empty($whitelist)) {
            return;
        }

        foreach ($whitelist as $allowedEntry) {
            if ($this->ipMatchesEntry($ip, $allowedEntry)) {
                return;
            }
        }

        throw new FraudGuardException('Your network is not permitted to check in on this device.');
    }

    private function ipMatchesEntry(string $ip, string $entry): bool
    {
        if (str_contains($entry, '/')) {
            return $this->ipInCidrRange($ip, $entry);
        }

        return $ip === $entry;
    }

    private function ipInCidrRange(string $ip, string $cidr): bool
    {
        [$subnet, $prefixLength] = explode('/', $cidr, 2);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $prefixLength = (int) $prefixLength;

        if ($ipLong === false || $subnetLong === false || $prefixLength < 0 || $prefixLength > 32) {
            return false;
        }

        $mask = $prefixLength === 0 ? 0 : (-1 << (32 - $prefixLength));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Great-circle distance between two coordinates, in meters.
     */
    private function haversineDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
