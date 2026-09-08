<?php

declare(strict_types=1);

namespace App\Modules\Sms\Gateways;

use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Jordan Telecom (MTC) HTTP SMS gateway.
 *
 * The MTC endpoint is a plain HTTP GET that returns a text body shaped
 * like "<code>@<detail>" — `0@...` means the message was accepted, any
 * other numeric prefix is a provider error. There is no JSON envelope
 * and no bearer token: credentials are query-string params. The endpoint
 * URL, timeout, credentials and sender ID all come from config so ops
 * can rotate them via env without a redeploy of the gateway.
 *
 * Every failure path (non-2xx response, non-zero provider code, thrown
 * exception) is caught and returned as `SmsResult::failure()` — see the
 * SmsGateway contract for why we never throw.
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
        $endpoint = $this->endpoint ?? 'http://int.mtcsms.com/sendsms.aspx';

        try {
            $response = Http::timeout($this->timeout)->get($endpoint, [
                'username' => (string) $this->username,
                'password' => (string) $this->password,
                'from' => $this->sender,
                'to' => $to,
                'msg' => $body,
                // MTC's `type` param: 0 = GSM-7 (plain ASCII/Latin), preserved
                // from V1. Unicode/Arabic bodies require a different `type`
                // value that MTC will document per account.
                'type' => 0,
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
}
