<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Contracts;

/**
 * Immutable value object returned by every WhatsappGateway::send() call.
 *
 * The gateway abstracts over provider-specific response formats — Meta
 * returns JSON with `messages[0].id`, the fake gateway synthesises a
 * UUID. All of that gets normalised into this fixed shape so downstream
 * code (WhatsappService, the SendWhatsappJob's log write, the
 * WhatsappChannel) never has to branch on the concrete gateway.
 *
 * `raw_response` is kept because operators sometimes need to reproduce a
 * failure verbatim when Meta disputes a delivery — we surface it from
 * the whatsapp_logs row rather than parse it upstream.
 */
final class WhatsappResult
{
    /**
     * @param  array<array-key, mixed>|null  $raw_response  Provider payload,
     *         verbatim where possible, otherwise `null` (e.g. transport
     *         exception with no body). Serialised to the
     *         `whatsapp_logs.raw_response` column as JSON.
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $provider_message_id = null,
        public readonly ?string $error = null,
        public readonly ?array $raw_response = null,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $raw_response
     */
    public static function success(?string $providerMessageId = null, ?array $raw_response = null): self
    {
        return new self(true, $providerMessageId, null, $raw_response);
    }

    /**
     * @param  array<array-key, mixed>|null  $raw_response
     */
    public static function failure(string $error, ?array $raw_response = null): self
    {
        return new self(false, null, $error, $raw_response);
    }
}
