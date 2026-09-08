<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Gateways;

use App\Modules\Whatsapp\Contracts\WhatsappGateway;
use App\Modules\Whatsapp\Contracts\WhatsappResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Meta (Facebook) WhatsApp Cloud API gateway.
 *
 * Sends a free-form text message via the Graph API:
 *   POST https://graph.facebook.com/v20.0/{PHONE_NUMBER_ID}/messages
 *   Authorization: Bearer {ACCESS_TOKEN}
 *   { messaging_product: "whatsapp", to: "+9627…", type: "text", text: { body: "…" } }
 *
 * On success the response is JSON shaped like:
 *   { "messaging_product": "whatsapp",
 *     "contacts": [ … ],
 *     "messages": [ { "id": "wamid.HBg…" } ] }
 * We surface `messages[0].id` as the `provider_message_id` so support
 * can trace a specific send in Meta's dashboards.
 *
 * On failure Meta returns a JSON envelope with an `error.message`,
 * `error.code` and `error.error_data` block; we forward the whole body
 * into `raw_response` and normalise the summary into `error`.
 *
 * Every failure path (non-2xx response, malformed body, thrown
 * exception) is caught and returned as `WhatsappResult::failure()` —
 * see the WhatsappGateway contract for why we never throw.
 */
final class MetaCloudGateway implements WhatsappGateway
{
    /**
     * @param  string       $accessToken   Long-lived Meta system-user or app
     *                                     access token. Sent as a Bearer.
     * @param  string       $phoneNumberId Numeric ID of the WhatsApp business
     *                                     phone number registered in Meta.
     * @param  string|null  $endpoint      Optional override of the Graph API
     *                                     base URL (used for testing / to pin a
     *                                     different API version). Defaults to
     *                                     the v20.0 production endpoint.
     * @param  int          $timeout       HTTP client timeout in seconds.
     */
    public function __construct(
        private readonly string $accessToken,
        private readonly string $phoneNumberId,
        private readonly ?string $endpoint = null,
        private readonly int $timeout = 10,
    ) {}

    public function send(string $to, string $body): WhatsappResult
    {
        // Guard the two identifiers here — an empty credential is a
        // deterministic config error, not a transport failure, so we don't
        // want to burn a retry against the Graph API to discover it.
        if ($this->accessToken === '' || $this->phoneNumberId === '') {
            return WhatsappResult::failure('config_missing_credentials');
        }

        $endpoint = $this->endpoint !== null && $this->endpoint !== ''
            ? rtrim($this->endpoint, '/').'/'.$this->phoneNumberId.'/messages'
            : 'https://graph.facebook.com/v20.0/'.$this->phoneNumberId.'/messages';

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->accessToken)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->normaliseRecipient($to),
                    'type' => 'text',
                    'text' => [
                        // `preview_url=false` prevents Meta from generating a
                        // link-preview card for URLs in the body — keeps the
                        // rendered message consistent regardless of link content.
                        'preview_url' => false,
                        'body' => $body,
                    ],
                ]);
        } catch (Throwable $e) {
            return WhatsappResult::failure('exception: '.$e->getMessage());
        }

        /** @var array<string, mixed>|null $json */
        $json = $response->json();
        $raw = [
            'status' => $response->status(),
            'body' => is_array($json) ? $json : $response->body(),
        ];

        if (! $response->successful()) {
            // Meta's error envelope: { "error": { "message": "...", "code": 190, ... } }
            $errorMessage = null;
            if (is_array($json) && isset($json['error']) && is_array($json['error'])) {
                $errorMessage = isset($json['error']['message']) && is_string($json['error']['message'])
                    ? $json['error']['message']
                    : null;
            }

            return WhatsappResult::failure(
                $errorMessage !== null && $errorMessage !== ''
                    ? 'provider: '.$errorMessage
                    : 'http_'.$response->status(),
                $raw,
            );
        }

        // Happy path — messages[0].id is the WAMID used for delivery
        // traces and DLR webhooks.
        $providerId = null;
        if (is_array($json)
            && isset($json['messages'][0]['id'])
            && is_string($json['messages'][0]['id'])
        ) {
            $providerId = $json['messages'][0]['id'];
        }

        return WhatsappResult::success($providerId, $raw);
    }

    /**
     * Meta accepts recipients with or without the leading `+`, but the
     * Graph API is stricter about whitespace and stray formatting. We
     * strip non-digits (preserving a leading `+`) so calls survive a
     * caller that passed "0791234567" alongside "+962791234567".
     */
    private function normaliseRecipient(string $to): string
    {
        $trimmed = trim($to);
        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        return $hasPlus ? '+'.$digits : $digits;
    }
}
