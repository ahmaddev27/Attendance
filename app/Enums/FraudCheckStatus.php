<?php

namespace App\Enums;

enum FraudCheckStatus: string
{
    case Passed = 'passed';
    case GpsFailed = 'gps_failed';
    case IpFailed = 'ip_failed';
    case Skipped = 'skipped';
}
