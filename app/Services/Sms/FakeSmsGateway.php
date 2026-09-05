<?php

namespace App\Services\Sms;

use App\DataObjects\SmsResult;

class FakeSmsGateway implements SmsGatewayInterface
{
    private array $sent = [];
    private ?string $failCode = null;

    public function send(string $to, string $message): SmsResult
    {
        $this->sent[] = ['to' => $to, 'message' => $message];

        if ($this->failCode !== null) {
            return SmsResult::failure($this->failCode);
        }

        return SmsResult::success('ok');
    }

    public function shouldFail(string $errorCode): void
    {
        $this->failCode = $errorCode;
    }

    public function sent(): array
    {
        return $this->sent;
    }
}
