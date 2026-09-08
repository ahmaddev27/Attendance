<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registered mobile-push endpoint for one (user, device) pair. Every
 * PushChannel send iterates the recipient's tokens and fires one Expo
 * push per row — a rotated / expired token is deleted on Expo's
 * DeviceNotRegistered receipt so the table stays lean.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 * @property ?string $device_id
 * @property ?string $device_name
 * @property ?\Illuminate\Support\Carbon $last_used_at
 */
class PushToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_id',
        'device_name',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
