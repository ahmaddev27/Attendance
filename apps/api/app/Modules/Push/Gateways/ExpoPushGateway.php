<?php

declare(strict_types=1);

namespace App\Modules\Push\Gateways;

use App\Modules\Push\Contracts\PushGateway;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Real Expo Push implementation. Talks to https://exp.host/--/api/v2/push/send
 * — the single public endpoint used by every Expo-managed React Native app
 * (both bare and managed workflow). No auth required for the ExponentPushToken
 * scheme; if you later switch to using an "access token" for FCM v1 direct,
 * pass it via the Bearer header (Expo supports this via its "Enhanced
 * Security" mode — off by default).
 *
 * Documented cap: 100 messages per request. We deliberately do not paginate
 * here; the caller (PushService) is responsible for chunking a larger batch
 * before invoking send().
 */
class ExpoPushGateway implements PushGateway
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ?string $accessToken = null,
    ) {
    }

    public function send(array $messages): array
    {
        if ($messages === []) {
            return ['data' => []];
        }

        $request = $this->http
            ->acceptJson()
            ->asJson()
            ->timeout(self::TIMEOUT_SECONDS);

        // Enhanced Security (optional) — sends the request with an
        // authenticated Expo access token so third-party apps can't
        // impersonate ours by guessing recipient tokens. Off by default.
        if ($this->accessToken !== null && $this->accessToken !== '') {
            $request = $request->withHeaders(['Authorization' => 'Bearer '.$this->accessToken]);
        }

        try {
            $response = $request->post(self::ENDPOINT, $messages);

            if (! $response->successful()) {
                // Log the raw body so ops can diff against Expo's error
                // schema; we swallow the exception intentionally so a
                // provider outage doesn't cascade into failed job retries
                // on every notification the queue processes.
                Log::warning('[push] Expo /send returned non-2xx', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['data' => []];
            }

            return $response->json() ?? ['data' => []];
        } catch (\Throwable $e) {
            Log::warning('[push] Expo /send threw', [
                'error' => $e->getMessage(),
                'count' => count($messages),
            ]);
            return ['data' => []];
        }
    }
}
