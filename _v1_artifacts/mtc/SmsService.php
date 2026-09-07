<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Jobs\SendSmsJob;
use App\Repositories\SmsLogRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsService
{
    public function __construct(
        private readonly SmsGatewayInterface $gateway,
        private readonly SmsLogRepository $logs,
    ) {}

    public function dispatch(string $phone, string $message): void
    {
        SendSmsJob::dispatch($phone, $message);
    }

    public function sendNow(string $phone, string $message): void
    {
        $result = $this->gateway->send($phone, $message);

        try {
            $this->logs->log(
                $phone,
                $message,
                $result->ok ? SmsStatus::Sent : SmsStatus::Failed,
                $result->providerResponse,
                $result->errorCode,
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
