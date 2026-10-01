<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Resources;

use App\Modules\Notifications\Services\NotificationPreferenceService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises the catalog + the user's stored overlay into the shape the
 * preferences page expects. The response is a FLAT list — one object per
 * event — with a `channels` map keyed by channel name → bool so the UI
 * can render it as a table without any extra plumbing.
 *
 * The wrapped resource is the array returned by
 * NotificationPreferenceService::allFor($user).
 */
class NotificationPreferenceMatrixResource extends JsonResource
{
    /**
     * @return array<int, array{event_key: string, label: string, channels: array<string, bool>}>
     */
    public function toArray($request): array
    {
        /** @var NotificationPreferenceService $service */
        $service = app(NotificationPreferenceService::class);
        $catalog = $service->knownEventKeys();

        /** @var array<string, bool> $stored */
        $stored = is_array($this->resource) ? $this->resource : [];

        $out = [];
        foreach ($catalog as $eventKey => $meta) {
            $channels = [];
            foreach (NotificationPreferenceService::CHANNELS as $channel) {
                $key = $eventKey.'.'.$channel;
                // Opt-OUT default: missing row == enabled.
                $channels[$channel] = $stored[$key] ?? true;
            }
            $out[] = [
                'event_key' => $eventKey,
                'label' => $meta['label'],
                'default_channels' => $meta['default_channels'],
                'channels' => $channels,
            ];
        }

        return $out;
    }
}
