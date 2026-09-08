<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Notifications\Channels;

use App\Modules\Whatsapp\Services\WhatsappService;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel that routes to the Meta WhatsApp Cloud
 * gateway.
 *
 * A notification opts in by returning this class' FQCN from `via()` and
 * implementing `toWhatsapp(mixed $notifiable): string`. The channel
 * resolves the recipient's phone from either:
 *   - `$notifiable->routeNotificationFor('whatsapp')` (standard Laravel hook), or
 *   - `$notifiable->employee->phone` (our User -> Employee link).
 *
 * When neither is available the channel silently no-ops: WhatsApp is
 * opt-in per-notification and the caller has already accepted the risk
 * that some recipients won't be reachable this way (an admin user
 * without an employee record, for example).
 */
final class WhatsappChannel
{
    public function __construct(
        private readonly WhatsappService $whatsapp,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $to = $this->resolvePhone($notifiable);
        if ($to === null || $to === '') {
            return;
        }

        /** @var string $body */
        $body = $notification->toWhatsapp($notifiable);
        if ($body === '') {
            return;
        }

        $this->whatsapp->send($to, $body);
    }

    private function resolvePhone(mixed $notifiable): ?string
    {
        if (is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')) {
            /** @var mixed $routed */
            $routed = $notifiable->routeNotificationFor('whatsapp', null);
            if (is_string($routed) && $routed !== '') {
                return $routed;
            }
        }

        // Fallback: our User model has an `employee` relation whose
        // `phone` column carries the MSISDN. Guard every hop because
        // both the relation and the column can legitimately be null.
        $phone = $notifiable->employee?->phone ?? null;

        return is_string($phone) && $phone !== '' ? $phone : null;
    }
}
