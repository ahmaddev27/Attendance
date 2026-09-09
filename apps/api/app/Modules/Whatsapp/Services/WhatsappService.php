<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappLog;
use App\Modules\Whatsapp\Contracts\WhatsappGateway;
use App\Modules\Whatsapp\Contracts\WhatsappResult;
use App\Modules\Whatsapp\Jobs\SendWhatsappJob;
use App\Shared\Support\RedactsSensitiveText;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Application-facing WhatsApp entry point.
 *
 * Two flows:
 *   - send()     : enqueue a SendWhatsappJob and return an accepted-result
 *                   marker. The default path for domain events (leave
 *                   decided, request pending, ...) — the caller doesn't
 *                   have to wait for the Graph API round-trip.
 *   - sendNow()  : run the gateway inline and log the outcome. For
 *                   critical, blocking flows and for tests where sync
 *                   behaviour is easier to assert on than inspecting
 *                   the queue.
 *
 * Both funnel through the container-resolved WhatsappGateway so the
 * concrete transport (Meta Cloud vs fake) is a single binding switch in
 * AppServiceProvider.
 */
final class WhatsappService
{
    use RedactsSensitiveText;

    public function __construct(
        private readonly WhatsappGateway $gateway,
    ) {}

    /**
     * Enqueue a WhatsApp message. Returns a synthetic "accepted"
     * WhatsappResult — the real provider response is captured in the
     * whatsapp_logs row when the queue worker runs the job.
     */
    public function send(string $to, string $body): WhatsappResult
    {
        SendWhatsappJob::dispatch($to, $body);

        return WhatsappResult::success(null, ['queued' => true]);
    }

    /**
     * Send synchronously. Failures here still write a whatsapp_logs row
     * and return a WhatsappResult::failure(); the caller decides whether
     * to surface the failure to the end-user.
     */
    public function sendNow(string $to, string $body): WhatsappResult
    {
        $result = $this->gateway->send($to, $body);

        try {
            // Persist the redacted body only — the raw copy still hit Meta
            // above. See SendSmsJob::writeLog for the same rule.
            WhatsappLog::create([
                'to' => $to,
                'body' => $this->redactBody($body),
                'status' => $result->success ? 'sent' : 'failed',
                'provider_message_id' => $result->provider_message_id,
                'error' => $result->error,
                'raw_response' => $result->raw_response,
            ]);
        } catch (Throwable $e) {
            // Same rationale as SendWhatsappJob::writeLog: the message
            // already hit Meta, a log-write failure must not raise or
            // the caller would incorrectly conclude nothing was sent.
            Log::error('[WhatsappService::sendNow] log write failed', [
                'to' => $to,
                'whatsapp_success' => $result->success,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
