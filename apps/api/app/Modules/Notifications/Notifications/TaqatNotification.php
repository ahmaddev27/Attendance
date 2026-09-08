<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Single generic in-app notification.
 *
 * Every domain event that wants to notify a user (leave approved, task
 * assigned, request returned, ...) constructs one of these instead of
 * introducing a per-event notification class — the payload is a fixed
 * shape ({title, body, url, icon, meta}) so both the API list endpoint
 * and the frontend bell can render any notification without a switch on
 * `type`. See NotificationService for the callers.
 *
 * Both delivery paths are used:
 *   - database  : durable, powers the /me/notifications list + unread count
 *   - broadcast : real-time push via Reverb; the bell subscribes on connect
 *                 and invalidates its react-query cache so a fresh
 *                 notification appears without waiting for the 30s poll.
 */
class TaqatNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta  Optional structured payload
     *                                       (leave_request_id, task_id, ...)
     * @param  bool  $suppressBroadcast  Set by NotificationService when the
     *                                    same event was already broadcast to
     *                                    the same recipient within the dedup
     *                                    window — the DB row is still written
     *                                    (durable inbox) but Reverb is skipped
     *                                    to avoid toast/counter double-fires.
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?string $url = null,
        public readonly ?string $icon = null,
        public readonly array $meta = [],
        public readonly bool $suppressBroadcast = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        // Database is always written — it's the durable inbox.
        // Broadcast is added when Reverb is configured AND the caller
        // didn't ask us to suppress it (see $suppressBroadcast).
        if ($this->suppressBroadcast || config('broadcasting.default') === 'null') {
            return ['database'];
        }
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
            'meta' => $this->meta,
        ];
    }

    /**
     * Payload delivered over Reverb. Kept identical to toArray() so the
     * bell can render the same DTO either way — it uses whichever it
     * receives first (broadcast for realtime, database on refresh).
     */
    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
