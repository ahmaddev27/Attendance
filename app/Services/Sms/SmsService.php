<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Jobs\SendSmsJob;
use App\Repositories\SmsLogRepository;

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

        $this->logs->log(
            $phone,
            $message,
            $result->ok ? SmsStatus::Sent : SmsStatus::Failed,
            $result->providerResponse,
            $result->errorCode,
        );
    }
}
