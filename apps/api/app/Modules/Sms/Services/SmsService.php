<?php

declare(strict_types=1);

namespace App\Modules\Sms\Services;

use App\Models\SmsLog;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use App\Modules\Sms\Jobs\SendSmsJob;
use App\Shared\Support\PhoneNumber;
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
 * AppServiceProvider, and both convert the recipient to the international
 * form the carrier dials: admins type local numbers (0599 123 456), and
 * MTCSMS silently never delivered those.
 */
final class SmsService
{
    use RedactsSensitiveText;

    /** MTCSMS is a Palestinian carrier; staff phones are 059/056 numbers. */
    private const DEFAULT_COUNTRY_CODE = '970';

    public function __construct(
        private readonly SmsGateway $gateway,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Enqueue an SMS. Returns a synthetic "accepted" SmsResult — the
     * real provider response is captured in the sms_logs row when the
     * queue worker runs the job.
     */
    public function send(string $to, string $body): SmsResult
    {
        $recipient = $this->recipient($to);

        if ($recipient === null) {
            return $this->rejectUnusablePhone($to, $body);
        }

        SendSmsJob::dispatch($recipient, $body);

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
        $recipient = $this->recipient($to);

        if ($recipient === null) {
            return $this->rejectUnusablePhone($to, $body);
        }

        $result = $this->gateway->send($recipient, $body);

        $this->log($recipient, $body, $result);

        return $result;
    }

    /**
     * The country code for locally typed numbers is editable from the
     * settings page, because the owner never touches the server env.
     */
    private function recipient(string $to): ?string
    {
        $countryCode = $this->settings->get(
            'sms.default_country_code',
            'services.mtc_sms.default_country_code',
        ) ?: self::DEFAULT_COUNTRY_CODE;

        return PhoneNumber::toInternational($to, $countryCode);
    }

    /**
     * A phone that is not a number never reaches the carrier, but it is
     * logged so the admin sees why the employee got nothing.
     */
    private function rejectUnusablePhone(string $to, string $body): SmsResult
    {
        $result = SmsResult::failure('invalid_phone');

        $this->log($to, $body, $result);

        return $result;
    }

    private function log(string $to, string $body, SmsResult $result): void
    {
        try {
            // Persist the redacted body only — the raw copy still hit the
            // carrier. See SendSmsJob::writeLog for the same rule.
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
            Log::error('[SmsService] log write failed', [
                'to' => $to,
                'sms_success' => $result->success,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
