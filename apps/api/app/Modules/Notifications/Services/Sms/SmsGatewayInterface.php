<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services\Sms;

use App\Shared\DataObjects\SmsResult;

/**
 * Ported unchanged (aside from namespace) from v1 — see
 * _v1_artifacts/mtc/SmsGatewayInterface.php. Bound to MtcSmsGateway in
 * production/development and FakeSmsGateway under testing — see
 * App\Modules\Notifications\NotificationsServiceProvider.
 */
interface SmsGatewayInterface
{
    public function send(string $to, string $message): SmsResult;
}
