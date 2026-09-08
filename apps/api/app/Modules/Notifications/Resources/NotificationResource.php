<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a DatabaseNotification for the API.
 *
 * The `data` column is a JSON blob written by TaqatNotification::toArray()
 * with a fixed shape ({title, body, url, icon, meta}). We spread it into
 * the top level here so the frontend doesn't have to reach into a nested
 * object for every field.
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($this->data) ? $this->data : (array) $this->data;

        return [
            'id' => $this->id,
            'title' => $payload['title'] ?? '',
            'body' => $payload['body'] ?? null,
            'url' => $payload['url'] ?? null,
            'icon' => $payload['icon'] ?? null,
            'meta' => $payload['meta'] ?? [],
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
