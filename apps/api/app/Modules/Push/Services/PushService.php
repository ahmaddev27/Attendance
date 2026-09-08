<?php

declare(strict_types=1);

namespace App\Modules\Push\Services;

use App\Models\PushToken;
use App\Modules\Push\Contracts\PushGateway;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the gateway with two responsibilities Notifications shouldn't have:
 *
 *   1. Chunking — Expo caps a single /send call at 100 messages; a bulk
 *      notify (e.g. "announcement to every active employee") is split
 *      into 100-row batches here so the caller doesn't have to know.
 *   2. Receipt handling — Expo returns per-message receipts inline in
 *      the /send response for immediate errors (invalid token, malformed
 *      payload, etc.). We match receipts back to their input row and
 *      delete any push_tokens row whose receipt says DeviceNotRegistered
 *      so a stale token doesn't waste one send-slot forever.
 */
class PushService
{
    private const CHUNK = 100;

    public function __construct(private readonly PushGateway $gateway)
    {
    }

    /**
     * Send `$payload` to every token that belongs to `$userId`. Returns
     * the number of messages the gateway accepted (not necessarily
     * delivered — Expo delivery status arrives asynchronously via a
     * receipt-id poll we intentionally skip for now; the client-side
     * subscription to broadcast is our real-time delivery channel).
     *
     * @param  array{
     *   title: string,
     *   body?: ?string,
     *   data?: array<string, mixed>,
     *   sound?: string,
     *   priority?: string,
     * }  $payload
     */
    public function sendToUser(int $userId, array $payload): int
    {
        $tokens = PushToken::query()->where('user_id', $userId)->get();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $messages = $tokens->map(fn (PushToken $t) => array_filter([
            'to' => $t->token,
            'title' => $payload['title'],
            'body' => $payload['body'] ?? null,
            'data' => $payload['data'] ?? null,
            'sound' => $payload['sound'] ?? 'default',
            'priority' => $payload['priority'] ?? 'high',
        ], static fn ($v) => $v !== null))->values();

        $accepted = 0;
        foreach ($messages->chunk(self::CHUNK) as $chunk) {
            $chunkArray = $chunk->all();
            $response = $this->gateway->send($chunkArray);
            $this->pruneDeadTokens($tokens, $chunkArray, $response);
            $accepted += count($chunkArray);
        }

        // Bump last_used_at so the mobile app can trim ancient tokens
        // on registration (see PushTokenController::register — a token
        // that hasn't been "used" in months is probably from an
        // uninstalled app and safe to overwrite).
        PushToken::query()->whereIn('id', $tokens->pluck('id'))->update([
            'last_used_at' => now(),
        ]);

        return $accepted;
    }

    /**
     * Walk Expo's receipt array; delete any push_tokens row whose
     * receipt says the device is no longer registered. Positional
     * matching between $chunkSent and $response['data'] is Expo's
     * documented contract — receipt N in the array is the outcome of
     * message N in the request.
     *
     * @param  \Illuminate\Support\Collection<int, PushToken>  $tokens
     * @param  array<int, array<string, mixed>>  $chunkSent
     * @param  array<string, mixed>  $response
     */
    private function pruneDeadTokens($tokens, array $chunkSent, array $response): void
    {
        $receipts = $response['data'] ?? [];
        if (! is_array($receipts) || $receipts === []) {
            return;
        }

        $tokensByString = $tokens->keyBy('token');

        foreach ($chunkSent as $i => $sent) {
            $receipt = $receipts[$i] ?? null;
            if (! is_array($receipt)) {
                continue;
            }

            $status = (string) ($receipt['status'] ?? '');
            $errorCode = (string) ($receipt['details']['error'] ?? '');

            if ($status === 'error' && ($errorCode === 'DeviceNotRegistered' || $errorCode === 'InvalidCredentials')) {
                $token = $sent['to'] ?? null;
                if (is_string($token) && $tokensByString->has($token)) {
                    $row = $tokensByString->get($token);
                    Log::info('[push] pruning dead token', [
                        'user_id' => $row->user_id,
                        'device_id' => $row->device_id,
                        'reason' => $errorCode,
                    ]);
                    $row->delete();
                }
            }
        }
    }
}
