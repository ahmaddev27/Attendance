<?php

declare(strict_types=1);

namespace App\Modules\Sms\Jobs;

use App\Models\SmsLog;
use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use App\Shared\Support\RedactsSensitiveText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued wrapper around SmsGateway::send().
 *
 * Runs on the default queue connection so the HTTP call to MTC never
 * blocks a web request. Each attempt persists an sms_logs row — success
 * OR failure — so the audit trail is complete even when the queue
 * worker eventually gives up.
 *
 * Retry policy:
 *   - up to `$tries` attempts, spaced by `backoff()` seconds
 *     (exponential: 10s, 30s, 90s). Enough to survive a transient
 *     carrier blip without hammering them or fanning out duplicate
 *     messages to the recipient.
 *
 * The gateway is contracted never to throw (see SmsGateway::send()) so
 * this job normally completes even for failed sends — we only bubble up
 * to the queue's retry pipeline when the log write itself blows up or
 * the container can't resolve the binding.
 */
final class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RedactsSensitiveText, SerializesModels;

    /**
     * Total attempts (initial + retries). Kept small because SMS is
     * user-visible: a delayed retry is usually more useful than a fifth
     * attempt after a minute of dead carrier.
     */
    public int $tries = 3;

    public function __construct(
        public readonly string $to,
        public readonly string $body,
    ) {}

    /**
     * Exponential backoff between retries, in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(SmsGateway $gateway): void
    {
        $result = $gateway->send($this->to, $this->body);

        $this->writeLog($result);

        // Non-transport failures (provider rejected the message, bad
        // credentials, ...) are not retried — the gateway already
        // classified them. Transport failures are surfaced via `error`
        // starting with `exception:` or `http_5xx` and DO retry.
        if (! $result->success && $this->shouldRetry($result->error)) {
            // Let the queue re-run this job per `$tries`/`backoff()`.
            $this->release($this->currentBackoff());
        }
    }

    /**
     * Persist the attempt. A failure here must NOT surface as a job
     * exception when the SMS itself already went out — the queue would
     * retry and the recipient would be double-messaged.
     */
    private function writeLog(SmsResult $result): void
    {
        try {
            // Persist the redacted body only — the raw copy still hit the
            // carrier via handle() above, but a DB dump / audit log must
            // never leak the plaintext password from a welcome SMS.
            SmsLog::create([
                'to' => $this->to,
                'body' => $this->redactBody($this->body),
                'status' => $result->success ? 'sent' : 'failed',
                'provider_message_id' => $result->provider_message_id,
                'error' => $result->error,
                'raw_response' => $result->raw_response,
            ]);
        } catch (Throwable $e) {
            Log::error('[SendSmsJob] log write failed', [
                'to' => $this->to,
                'sms_success' => $result->success,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shouldRetry(?string $error): bool
    {
        if ($error === null) {
            return false;
        }

        // Transport-shaped failures: exception during HTTP call, or 5xx
        // response. Everything else (provider_401, provider_bad_number)
        // is deterministic and won't succeed on retry.
        return str_starts_with($error, 'exception:')
            || str_starts_with($error, 'http_5');
    }

    /**
     * The delay for the CURRENT attempt from the backoff schedule.
     * `attempts()` starts at 1 for the first run, so we index into the
     * matching backoff slot (clamped to the last one).
     */
    private function currentBackoff(): int
    {
        $schedule = $this->backoff();
        $index = max(0, min($this->attempts() - 1, count($schedule) - 1));

        return $schedule[$index];
    }
}
