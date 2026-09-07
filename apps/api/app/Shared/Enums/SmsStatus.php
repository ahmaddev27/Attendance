<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Outcome of a single outbound SMS send attempt, as logged to
 * `sms_logs` by SmsService — mirrors the v1 App\Enums\SmsStatus this was
 * ported from (see _v1_artifacts/mtc/).
 */
enum SmsStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
