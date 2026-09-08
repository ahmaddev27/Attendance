<?php

declare(strict_types=1);

namespace App\Modules\Push\Notifications\Channels;

use App\Modules\Push\Services\PushService;
use Illuminate\Notifications\Notification;

/**
 * Custom notification channel that hands the payload to PushService.
 *
 * A notification opts into this channel by returning
 * `App\Modules\Push\Notifications\Channels\PushChannel::class` from its
 * `via()` array AND implementing `toPush($notifiable): array` with
 * `{title, body?, data?}`. TaqatNotification provides both — see the
 * `$sendPush` flag there.
 */
class PushChannel
{
    public function __construct(private readonly PushService $service)
    {
    }

    /**
     * Called by Laravel's NotificationSender for every notifiable that
     * has this channel in its via(). We only send to the notifiable's
     * own user tokens — no cross-user leakage even if a caller
     * accidentally passes an admin User for a broadcast.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush')) {
            return;
        }

        $payload = $notification->toPush($notifiable);
        if (! is_array($payload) || empty($payload['title'])) {
            return;
        }

        $userId = (int) ($notifiable->id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $this->service->sendToUser($userId, $payload);
    }
}
