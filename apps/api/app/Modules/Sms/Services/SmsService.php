<?php

declare(strict_types=1);

namespace App\Modules\Sms\Services;

use App\Models\SmsLog;
use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use App\Modules\Sms\Jobs\SendSmsJob;
use App\Shared\Support\RedactsSensitiveText;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Application-facing SMS entry point.
 *
 * Two flows:
 *   - send()     : enqueue a SendSmsJob and return an accepted-result
 *                   marker. The default path for domain events (leave
 *                   decided, request pending, ...) — the caller doesn't
 *                   have to wait for the carrier.
 *   - sendNow()  : run the gateway inline and log the outcome. For
 *                   critical, blocking flows (OTP, 2FA) and for tests
 *                   where sync behaviour is easier to assert on than
 *                   inspecting the queue.
 *
 * Both funnel through the container-resolved SmsGateway so the concrete
 * transport (MTC vs fake) is a single binding switch in
 * AppServiceProvider.
 */
final class SmsService
{
    use RedactsSensitiveText;

    public function __construct(
        private readonly SmsGateway $gateway,
    ) {}

    /**
     * Enqueue an SMS. Returns a synthetic "accepted" SmsResult — the
     * real provider response is captured in the sms_logs row when the
     * queue worker runs the job.
     */
    public function send(string $to, string $body): SmsResult
    {
        SendSmsJob::dispatch($to, $body);

        return SmsResult::success(null, ['queued' => true]);
    }

    /**
     * Send synchronously. Failures here still write an sms_logs row and
     * return an SmsResult::failure(); the caller decides whether to
     * surface the failure to the end-user (OTP flow would; a fire-and-
     * forget notification would not).
     */
    public function sendNow(string $to, string $body): SmsResult
    {
        $result = $this->gateway->send($to, $body);

        try {
            // Persist the redacted body only — the raw copy still hit the
            // carrier above. See SendSmsJob::writeLog for the same rule.
            SmsLog::create([
                'to' => $to,
                'body' => $this->redactBody($body),
                'status' => $result->success ? 'sent' : 'failed',
                'provider_message_id' => $result->provider_message_id,
                'error' => $result->error,
                'raw_response' => $result->raw_response,
            ]);
        } catch (Throwable $e) {
            // Same rationale as SendSmsJob::writeLog: the SMS already
            // hit the carrier, a log-write failure must not raise.
            Log::error('[SmsService::sendNow] log write failed', [
                'to' => $to,
                'sms_success' => $result->success,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }
}
