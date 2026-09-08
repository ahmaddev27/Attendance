<?php

declare(strict_types=1);

namespace App\Modules\Push\Gateways;

use App\Modules\Push\Contracts\PushGateway;
use Illuminate\Support\Facades\Log;

/**
 * Test-double for the push gateway — writes every message to the log
 * instead of hitting Expo, and returns a synthetic "ok" receipt for
 * every input row so PushService's downstream logic (token pruning on
 * DeviceNotRegistered) can still be exercised locally.
 *
 * Wired in App\Providers\AppServiceProvider whenever the app runs in
 * `testing` mode, when there is no Expo access token configured, or
 * when the `push.fake` setting is explicitly enabled for local /
 * staging smoke tests.
 */
class FakePushGateway implements PushGateway
{
    public function send(array $messages): array
    {
        Log::info('[push:fake] would deliver', [
            'count' => count($messages),
            'sample' => $messages[0] ?? null,
        ]);

        return [
            'data' => array_map(
                fn (array $m) => ['status' => 'ok', 'id' => 'fake-'.uniqid()],
                $messages,
            ),
        ];
    }
}
