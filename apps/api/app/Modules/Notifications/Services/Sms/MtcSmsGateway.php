<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services\Sms;

use App\Modules\Notifications\Services\SettingsService;
use App\Shared\DataObjects\SmsResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Ported unchanged from v1 (see _v1_artifacts/mtc/MtcSmsGateway.php)
 * aside from the namespace move and pointing SettingsService at its new
 * home — MTC's response body is always "{code}@{message}"; a leading
 * "0" means success, anything else (including an HTTP-level failure or
 * a thrown exception) is reported as a failure with that code so
 * SmsService can log exactly what went wrong.
 */
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
