<?php

declare(strict_types=1);

namespace App\Modules\Sms\Gateways;

use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Jordan Telecom (MTC) HTTPS SMS gateway.
 *
 * The MTC endpoint returns a text body shaped like "<code>@<detail>" —
 * `0@...` means the message was accepted, any other numeric prefix is
 * a provider error. There is no JSON envelope and no bearer token:
 * credentials are form-encoded body params on the POST request. The
 * endpoint URL, timeout, credentials and sender ID all come from
 * config so ops can rotate them via env without a redeploy.
 *
 * Transport: we POST to `https://sms.mtcegypt.com.eg/sendsms.aspx`
 * with the credentials in the request body — never in the URL —
 * because:
 *   1. HTTP (the legacy V1 URL) is plaintext on the wire.
 *   2. GET puts credentials in the query string, which Laravel's
 *      HTTP client captures verbatim into logs, reverse-proxy access
 *      logs, and any Http::fake() debug output.
 *   3. TLS + form body keeps them off both the wire and the log.
 *
 * The legacy V1 endpoint (`http://int.mtcsms.com/sendsms.aspx`) still
 * works for backward-compat if an operator points the setting there,
 * but new deployments should use the HTTPS V2 host — the endpoint
 * validator now requires an `https://` URL.
 *
 * Every failure path (non-2xx response, non-zero provider code, thrown
 * exception) is caught and returned as `SmsResult::failure()` — see the
 * SmsGateway contract for why we never throw.
 *
 * MTC's `type` param encodes the message encoding: 0 = GSM-7 (plain
 * ASCII/Latin). The Unicode/Arabic code can differ per account — some
 * accounts use `1`, others `2`. Override via the MTC_SMS_UNICODE_TYPE
 * env var when the default (1) is wrong for a given tenant. Arabic
 * (or any non-ASCII) body is detected automatically and routed through
 * the Unicode `type` value; the previous hardcoded `0` garbled the
 * welcome SMS on every non-Latin body.
 */
final class MtcSmsGateway implements SmsGateway
{
    /**
     * @param  string|null  $username  Provider account username (env-driven).
     * @param  string|null  $password  Provider account password (env-driven).
     * @param  string       $sender    From/sender ID shown on the recipient's
     *                                 handset. MTC accounts are provisioned
     *                                 with a whitelist of allowed IDs.
     * @param  string|null  $endpoint  Full URL for the send-SMS endpoint.
     *                                 Defaults to the V1 URL when null.
     * @param  int          $timeout   HTTP client timeout in seconds.
     */
    public function __construct(
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly string $sender = 'TAQAT',
        private readonly ?string $endpoint = null,
        private readonly int $timeout = 10,
    ) {}

    public function send(string $to, string $body): SmsResult
    {
        // `?? default` isn't enough: AppServiceProvider casts the settings
        // lookup to (string), so a missing/blank setting arrives here as
        // "" — which is NOT null, so ?? leaves the empty string in place
        // and Guzzle throws "URI must include a scheme and host". Empty
        // OR null → fallback to the pinned HTTPS V2 URL so a fresh
        // install never sends credentials over plaintext HTTP.
        $endpoint = ($this->endpoint !== null && $this->endpoint !== '')
            ? $this->endpoint
            : 'https://sms.mtcegypt.com.eg/sendsms.aspx';

        try {
            // POST with `asForm()` so credentials + message body go in
            // the request body as application/x-www-form-urlencoded —
            // NOT in the URL. This keeps them out of the Laravel HTTP
            // client's default request logging (which captures the URL
            // verbatim, query string and all) and out of any upstream
            // reverse-proxy access log. TLS covers the wire itself.
            $response = Http::timeout($this->timeout)->asForm()->post($endpoint, [
                'username' => (string) $this->username,
                'password' => (string) $this->password,
                'from' => $this->sender,
                'to' => $to,
                'msg' => $body,
                // 0 = GSM-7 (plain ASCII/Latin); non-ASCII bodies (Arabic,
                // emoji, …) are auto-routed to the Unicode `type` value,
                // env-tunable via MTC_SMS_UNICODE_TYPE per account.
                'type' => $this->detectSmsType($body),
            ]);
        } catch (Throwable $e) {
            return SmsResult::failure('exception: '.$e->getMessage());
        }

        $bodyText = trim($response->body());
        $raw = ['status' => $response->status(), 'body' => $bodyText];

        if (! $response->successful()) {
            return SmsResult::failure('http_'.$response->status(), $raw);
        }

        // MTC's response format is `<code>@<message-id-or-detail>` — 0 means
        // accepted, anything else is a provider-side rejection.
        $parts = explode('@', $bodyText, 2);
        $code = $parts[0] ?? '';
        $detail = $parts[1] ?? null;

        if ($code === '0') {
            return SmsResult::success($detail !== null && $detail !== '' ? $detail : null, $raw);
        }

        return SmsResult::failure('provider_'.$code, $raw);
    }

    /**
     * Return the correct MTC `type` value for the given body: 0 when the
     * body is pure GSM-7 (ASCII), otherwise the account's Unicode code
     * (env-tunable, default 1). Arabic and other non-Latin scripts MUST
     * be sent as Unicode or the receiving handset shows garbage.
     */
    private function detectSmsType(string $body): int
    {
        $isUnicode = preg_match('/[^\x00-\x7F]/', $body) === 1;

        if (! $isUnicode) {
            return 0;
        }

        return (int) (env('MTC_SMS_UNICODE_TYPE', 1));
    }
}
