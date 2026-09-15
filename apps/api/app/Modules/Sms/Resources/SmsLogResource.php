<?php

declare(strict_types=1);

namespace App\Modules\Sms\Resources;

use App\Models\SmsLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SmsLog
 */
class SmsLogResource extends JsonResource
{
    /** Enough of the carrier's answer to read the error, never a full dump. */
    private const PROVIDER_RESPONSE_LENGTH = 200;

    /**
     * The body is left out: even redacted it carries names, and the status,
     * error and carrier answer are what explain a missing message.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'to' => $this->maskedRecipient((string) $this->to),
            'status' => $this->status,
            'error' => $this->error,
            'provider_message_id' => $this->provider_message_id,
            'provider_response' => $this->providerResponse(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Keeps the country code and the last digits so an admin can tell
     * which employee it was without the settings page listing full numbers.
     */
    private function maskedRecipient(string $to): string
    {
        $length = mb_strlen($to);

        if ($length <= 6) {
            return str_repeat('*', max(0, $length - 2)).mb_substr($to, -2);
        }

        return mb_substr($to, 0, 3).str_repeat('*', $length - 6).mb_substr($to, -3);
    }

    private function providerResponse(): ?string
    {
        $body = is_array($this->raw_response) ? ($this->raw_response['body'] ?? null) : null;

        return is_string($body) && $body !== '' ? mb_substr($body, 0, self::PROVIDER_RESPONSE_LENGTH) : null;
    }
}
