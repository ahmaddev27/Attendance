<?php

namespace App\Repositories;

use App\Enums\SmsStatus;
use App\Models\SmsLog;

class SmsLogRepository
{
    public function log(string $phone, string $message, SmsStatus $status, ?string $response, ?string $errorCode): SmsLog
    {
        return SmsLog::create([
            'phone' => $phone,
            'message' => $message,
            'status' => $status,
            'provider_response' => $response,
            'error_code' => $errorCode,
            'sent_at' => now(),
        ]);
    }
}
