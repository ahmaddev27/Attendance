<?php

declare(strict_types=1);

namespace App\Modules\Sms\Gateways;

use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * MTCSMS gateway (Modern Tech Corp, mtcsms.com — a Palestinian bulk SMS
 * provider).
 *
 * The endpoint returns a text body shaped like "<code>@<detail>" —
 * `0@...` means the message was accepted, any other numeric prefix is
 * a provider error (e.g. `10003@ONE OR MORE FIELDS IS EMPTY`). There is
 * no JSON envelope and no bearer token: credentials are form-encoded
 * body params on the POST request. Credentials, sender ID, endpoint and
 * the Unicode type come from the settings page (falling back to config).
 *
 * Transport: we POST to `https://int.mtcsms.com/sendsms.aspx` with the
 * credentials in the request body — never in the URL — because:
 *   1. HTTP is plaintext on the wire.
 *   2. GET puts credentials in the query string, which Laravel's
 *      HTTP client captures verbatim into logs, reverse-proxy access
 *      logs, and any Http::fake() debug output.
 *   3. TLS + form body keeps them off both the wire and the log.
 *
 * The previous default, sms.mtcegypt.com.eg, does not resolve at all
 * (checked 2026-09-15), so every send from an install whose endpoint
 * setting was left blank failed; int.mtcsms.com answers over HTTPS.
 *
 * Every failure path (non-2xx response, non-zero provider code, thrown
 * exception) is caught and returned as `SmsResult::failure()` — see the
 * SmsGateway contract for why we never throw.
 *
 * The `type` param encodes the message encoding: 0 = GSM-7 (plain
 * ASCII/Latin). The Unicode/Arabic code can differ per account — some
 * accounts use `1`, others `2` — so it is a setting. Arabic (or any
 * non-ASCII) body is detected automatically and routed through it.
 */
final class MtcSmsGateway implements SmsGateway
{
    public const DEFAULT_ENDPOINT = 'https://int.mtcsms.com/sendsms.aspx';

    /**
     * @param  string|null  $username  Provider account username (env-driven).
     * @param  string|null  $password  Provider account password (env-driven).
     * @param  string  $sender  From/sender ID shown on the recipient's
     *                          handset. MTC accounts are provisioned
     *                          with a whitelist of allowed IDs.
     * @param  string|null  $endpoint  Full URL for the send-SMS endpoint.
     *                                 DEFAULT_ENDPOINT when null or blank.
     * @param  int  $timeout  HTTP client timeout in seconds.
     * @param  int  $unicodeType  The account's `type` value for
     *                            non-ASCII (Arabic) messages.
     */
    public function __construct(
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly string $sender = 'TAQAT',
        private readonly ?string $endpoint = null,
        private readonly int $timeout = 10,
        private readonly int $unicodeType = 1,
    ) {}

    public function send(string $to, string $body): SmsResult
    {
        // `?? default` isn't enough: AppServiceProvider casts the settings
        // lookup to (string), so a missing/blank setting arrives here as
        // "" — which is NOT null, so ?? leaves the empty string in place
        // and Guzzle throws "URI must include a scheme and host". Empty
        // OR null → the provider's HTTPS endpoint, so a fresh install
        // works without anyone filling the endpoint field.
        $endpoint = ($this->endpoint !== null && $this->endpoint !== '')
            ? $this->endpoint
            : self::DEFAULT_ENDPOINT;

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
     * body is pure GSM-7 (ASCII), otherwise the account's Unicode code.
     * Arabic and other non-Latin scripts MUST be sent as Unicode or the
     * receiving handset shows garbage.
     */
    private function detectSmsType(string $body): int
    {
        $isUnicode = preg_match('/[^\x00-\x7F]/', $body) === 1;

        return $isUnicode ? $this->unicodeType : 0;
    }
}
