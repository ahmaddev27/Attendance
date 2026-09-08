<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Gateways;

use App\Modules\Whatsapp\Contracts\WhatsappGateway;
use App\Modules\Whatsapp\Contracts\WhatsappResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * No-op WhatsApp gateway that writes to the Laravel log instead of
 * hitting Meta's Graph API. Bound in AppServiceProvider whenever
 *   - the application is running in the `testing` environment, or
 *   - `config('services.whatsapp.fake')` (or the DB-backed
 *     `whatsapp.fake` setting) is truthy (local dev without an
 *     approved WhatsApp Business number, staging smoke tests, ...).
 *
 * Always returns success with a synthesised `provider_message_id` so
 * the downstream whatsapp_logs row and any test assertions see a
 * stable shape.
 */
final class FakeWhatsappGateway implements WhatsappGateway
{
    public function send(string $to, string $body): WhatsappResult
    {
        $fakeId = 'fake-wa-'.Str::uuid()->toString();

        Log::info('[FakeWhatsappGateway] WhatsApp delivered (no provider hit)', [
            'to' => $to,
            'body' => $body,
            'provider_message_id' => $fakeId,
        ]);

        return WhatsappResult::success($fakeId, [
            'driver' => 'fake',
            'to' => $to,
            'body' => $body,
        ]);
    }
}
