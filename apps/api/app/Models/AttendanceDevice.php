<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttendanceDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AttendanceDevice extends Model
{
    /** @use HasFactory<AttendanceDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'qr_token',
        'qr_rotates_every_seconds',
        'last_token_rotated_at',
        'allowed_lat',
        'allowed_lng',
        'allowed_radius_meters',
        'ip_whitelist',
        'enforce_geo',
        'enforce_ip',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ip_whitelist' => 'array',
            'allowed_lat' => 'decimal:7',
            'allowed_lng' => 'decimal:7',
            'qr_rotates_every_seconds' => 'integer',
            'allowed_radius_meters' => 'integer',
            'last_token_rotated_at' => 'datetime',
            'enforce_geo' => 'boolean',
            'enforce_ip' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(Attendance::class, 'check_in_device_id');
    }

    public function checkOuts(): HasMany
    {
        return $this->hasMany(Attendance::class, 'check_out_device_id');
    }

    /**
     * Issue a fresh QR token and reset the rotation clock.
     */
    public function rotateToken(): void
    {
        $this->forceFill([
            'qr_token' => Str::random(64),
            'last_token_rotated_at' => now(),
        ])->save();
    }

    /**
     * Whether the current token is older than its allowed rotation window.
     * QR rotation was removed as a product feature: tokens are permanent
     * for the life of the device. The method is preserved so callers
     * that still ask (QrTokenService::resolveDevice) don't need touching,
     * but the answer is unconditionally false — old rows on the server
     * still carrying a non-zero qr_rotates_every_seconds must not throw
     * "QR expired" against a printed poster the admin never intended
     * to time out.
     */
    public function isTokenExpired(): bool
    {
        return false;
    }
}
