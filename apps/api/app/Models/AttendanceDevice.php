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
     * A device that has never had a token rotated is treated as expired.
     *
     * Setting `qr_rotates_every_seconds = 0` disables expiry entirely —
     * the token stays valid forever until the admin manually rotates.
     * Handy for a printed QR poster where you don't want an operator
     * chasing rotations.
     */
    public function isTokenExpired(): bool
    {
        if ($this->qr_rotates_every_seconds === 0) {
            return false;
        }

        if (! $this->last_token_rotated_at) {
            return true;
        }

        return $this->last_token_rotated_at
            ->copy()
            ->addSeconds($this->qr_rotates_every_seconds)
            ->isPast();
    }
}
