<?php

declare(strict_types=1);

namespace App\Modules\Sms\Contracts;

/**
 * Immutable value object returned by every SmsGateway::send() call.
 *
 * The gateway abstracts over provider-specific response formats — MTC
 * returns a plain-text `code@detail` body, a future SMPP provider might
 * return JSON, and the fake gateway synthesises a UUID. All of that gets
 * normalised into this fixed shape so downstream code (SmsService, the
 * SendSmsJob's log write, the SmsChannel) never has to branch on the
 * concrete gateway.
 *
 * `raw_response` is kept because operators sometimes need to reproduce a
 * failure verbatim when a carrier disputes a delivery — we surface it
 * from the SmsLog row rather than parse it upstream.
 */
final class SmsResult
{
    /**
     * @param  array<array-key, mixed>|null  $raw_response  Provider payload,
     *         verbatim where possible, otherwise `null` (e.g. transport
     *         exception with no body). Serialised to the `sms_logs.raw_response`
     *         column as JSON.
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
