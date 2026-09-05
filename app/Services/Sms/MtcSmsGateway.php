<?php

namespace App\Services\Sms;

use App\DataObjects\SmsResult;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Throwable;

class MtcSmsGateway implements SmsGatewayInterface
{
    public function __construct(private readonly SettingsService $settings) {}

    public function send(string $to, string $message): SmsResult
    {
        try {
            $response = Http::timeout(config('sms.timeout'))->get(config('sms.endpoint'), [
                'username' => $this->settings->get('sms_username'),
                'password' => $this->settings->get('sms_password'),
                'from' => $this->settings->get('sms_sender'),
                'to' => $to,
                'msg' => $message,
                'type' => 0,
            ]);

            if (! $response->successful()) {
                return SmsResult::failure('http_'.$response->status(), $response->body());
            }

            $body = trim($response->body());
            $code = explode('@', $body)[0] ?? '';

            return $code === '0'
                ? SmsResult::success($body)
                : SmsResult::failure($code, $body);
        } catch (Throwable $e) {
            return SmsResult::failure('exception', $e->getMessage());
        }
    }
}
