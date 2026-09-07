<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services\Sms;

use App\Modules\Notifications\Jobs\SendSmsJob;
use App\Modules\Notifications\Repositories\SmsLogRepository;
use App\Shared\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ported from v1 (see _v1_artifacts/mtc/SmsService.php): orchestrates
 * the gateway call and its audit log entry. $notifiable is the optional
 * polymorphic source of the message (a User, an Employee, ...) recorded
 * on the sms_logs row — see 2026_09_11_100002_create_sms_logs_table.php.
 */
class SmsService
{
    public function __construct(
        private readonly SmsGatewayInterface $gateway,
        private readonly SmsLogRepository $logs,
    ) {}

    /**
     * Queues the send so a slow/unreachable gateway never blocks the
     * caller (an HTTP request or an event listener).
     */
    public function dispatch(string $phone, string $message, ?Model $notifiable = null): void
    {
        SendSmsJob::dispatch($phone, $message, $notifiable);
    }

    /**
     * Sends synchronously — used directly by SendSmsJob::handle(), and
     * available to call sites that need to know the outcome immediately
     * rather than fire-and-forget via dispatch().
     */
    public function sendNow(string $phone, string $message, ?Model $notifiable = null): void
    {
        $result = $this->gateway->send($phone, $message);

        try {
            $this->logs->log(
                $phone,
                $message,
                $result->ok ? SmsStatus::Sent : SmsStatus::Failed,
                $result->providerResponse,
                $result->errorCode,
                $notifiable,
            );
        } catch (Throwable $e) {
            // The SMS was already sent by the gateway; a failed log write must not
            // cause the queue worker to retry this job and resend the message.
            Log::error('SMS log write failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'sms_sent' => $result->ok,
            ]);
        }
    }
}
