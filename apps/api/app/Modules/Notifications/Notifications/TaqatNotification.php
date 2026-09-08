<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
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
 */
class TaqatNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta  Optional structured payload
     *                                       (leave_request_id, task_id, ...)
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?string $url = null,
        public readonly ?string $icon = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        // Database only for M7. Broadcast (Reverb) and mail land in a
        // follow-up commit once the front-end bell + queue worker are
        // both proven end-to-end.
        return ['database'];
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
}
