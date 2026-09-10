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
        // Admin toggle off → skip the entire check. Location is never
        // requested nor validated on this device.
        if (! $device->enforce_geo) {
            return;
        }

        // Admin toggle on but the device isn't fully configured (missing
        // anchor coords or radius) → fail-open. This is a misconfig,
        // not a user-permission problem, and the previous "location is
        // required" error trained users to keep re-granting GPS access
        // when the real fix was for the admin to fill in the coords.
        // Scan is allowed; ops can spot the half-configured device in
        // the admin panel (the row visibly says "enforce_geo: on" with
        // no coordinates) and either finish the config or toggle it back
        // off. No security risk — enforcement was already misconfigured.
        if (
            $device->allowed_lat === null
            || $device->allowed_lng === null
            || ! $device->allowed_radius_meters
        ) {
            return;
        }

        // Fully-configured device → the user MUST send a location. This
        // is the only path that actually rejects a scan.
        if ($latitude === null || $longitude === null) {
            throw new FraudGuardException('يجب تفعيل خدمة الموقع في المتصفح لإتمام تسجيل الحضور على هذا الجهاز.');
        }

        $distanceMeters = $this->haversineDistanceMeters(
            (float) $device->allowed_lat,
            (float) $device->allowed_lng,
            $latitude,
            $longitude,
        );

        if ($distanceMeters > $device->allowed_radius_meters) {
            throw new FraudGuardException('أنت خارج النطاق المسموح لتسجيل الحضور على هذا الجهاز.');
        }
    }

    private function assertIpAllowed(AttendanceDevice $device, string $ip): void
    {
        // Same admin-toggle gate as geofence. A stored whitelist without
        // enforce_ip=true is treated as informational — visible in the
        // admin UI but not enforced at scan time.
        if ($device->enforce_ip === false) {
            return;
        }

        $whitelist = $device->ip_whitelist;

        // enforce_ip=true with no whitelist is a misconfiguration — refuse
        // to fail-open so the admin is forced to configure the allowlist.
        if (empty($whitelist)) {
            throw new FraudGuardException('IP whitelist not configured for this device.');
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
