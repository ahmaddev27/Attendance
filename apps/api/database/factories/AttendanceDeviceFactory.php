<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttendanceDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendanceDevice>
 */
class AttendanceDeviceFactory extends Factory
{
    protected $model = AttendanceDevice::class;

    public function definition(): array
    {
        return [
            'name' => 'Main Office',
            'qr_token' => Str::random(64),
            'qr_rotates_every_seconds' => 300,
            'last_token_rotated_at' => now(),
            'allowed_lat' => null,
            'allowed_lng' => null,
            'allowed_radius_meters' => null,
            'ip_whitelist' => null,
            'is_active' => true,
        ];
    }

    /**
     * Restrict the device to a geofence around the given coordinates.
     */
    public function withGeofence(float $lat, float $lng, int $radiusMeters = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_lat' => $lat,
            'allowed_lng' => $lng,
            'allowed_radius_meters' => $radiusMeters,
        ]);
    }

    public function withIpWhitelist(array $ips): static
    {
        return $this->state(fn (array $attributes) => ['ip_whitelist' => $ips]);
    }

    public function tokenRotatedAt(\DateTimeInterface $when): static
    {
        return $this->state(fn (array $attributes) => ['last_token_rotated_at' => $when]);
    }
}
