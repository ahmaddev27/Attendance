<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

/**
 * Raised by FraudGuardService when a scan fails geofence or IP-whitelist
 * verification. Mapped to 403 Forbidden.
 */
class FraudGuardException extends AttendanceModuleException
{
    public function statusCode(): int
    {
        return 403;
    }
}
