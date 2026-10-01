<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (user, event_key, channel) tuple the user has explicitly
 * flipped. The absence of a row means "enabled" — see
 * {@see \App\Modules\Notifications\Services\NotificationPreferenceService}.
 *
 * @property int $id
 * @property int $user_id
 * @property string $event_key
 * @property string $channel
 * @property bool $enabled
 */
class NotificationPreference extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event_key',
        'channel',
        'enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
