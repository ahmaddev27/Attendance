<?php

namespace App\Services\Sms;

use App\DataObjects\SmsResult;

interface SmsGatewayInterface
{
    public function send(string $to, string $message): SmsResult;
}
