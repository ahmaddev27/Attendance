<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum ScanPinSource: string
{
    case AdminReset = 'admin_reset';
    case BulkIssue = 'bulk_issue';
    case SelfService = 'self_service';
}
