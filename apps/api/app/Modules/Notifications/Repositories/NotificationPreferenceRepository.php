<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A user with no row for a given (notification_type, channel) pair has
 * never touched that setting and is therefore still on the default —
 * enabled — so `notification_preferences` only ever grows rows for
 * combinations someone has explicitly turned off. This is what lets a
 * freshly created user see every channel enabled with zero seeding.
 */
class NotificationPreferenceRepository
{
    public function isEnabled(User $user, string $notificationType, string $channel): bool
    {
        return $this->forUser($user)
            ->first(fn (NotificationPreference $preference) => $preference->notification_type === $notificationType
                && $preference->channel->value === $channel
            )?->enabled ?? true;
    }

    /**
     * @return Collection<int, NotificationPreference>
     */
    public function forUser(User $user): Collection
    {
        return NotificationPreference::query()->where('user_id', $user->id)->get();
    }

    /**
     * @param  array<int, array{notification_type: string, channel: string, enabled: bool}>  $preferences
     */
    public function updateMany(User $user, array $preferences): void
    {
        foreach ($preferences as $preference) {
            NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type' => $preference['notification_type'],
                    'channel' => $preference['channel'],
                ],
                ['enabled' => $preference['enabled']],
            );
        }
    }
}
