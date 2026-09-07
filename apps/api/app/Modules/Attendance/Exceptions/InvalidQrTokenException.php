<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

/**
 * Raised when a scan presents a QR token that does not match any active
 * device, or that has rotated past its validity window. Mapped to 401
 * Unauthorized — the caller has no valid credential for this device.
 */
class InvalidQrTokenException extends AttendanceModuleException
{
    public function statusCode(): int
    {
        return 401;
    }
}
