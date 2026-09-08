<?php

declare(strict_types=1);

namespace App\Modules\Push\Contracts;

/**
 * Adapter around the concrete push provider (Expo, FCM, APNs). The
 * bulk-send shape lets us hand the whole recipient batch to Expo in a
 * single request (their /send endpoint accepts up to 100 messages per
 * call), so a "notify all shift managers" fires one HTTP call instead
 * of one per manager.
 *
 * A message is `{to, title, body, data, sound, priority}`. Return the
 * receipt payload verbatim so the caller (PushService) can inspect for
 * DeviceNotRegistered and prune the offending token from push_tokens.
 */
interface PushGateway
{
    /**
     * Send one push per row in $messages. Returns the raw response
     * envelope (`{data: [{status, id?, message?, details?}]}`) so a
     * higher layer can react to per-message receipts.
     *
     * @param  array<int, array{
     *   to: string,
     *   title: string,
     *   body?: string,
     *   data?: array<string, mixed>,
     *   sound?: string,
     *   priority?: string,
     * }>  $messages
     *
     * @return array<string, mixed>
     */
    public function send(array $messages): array;
}
