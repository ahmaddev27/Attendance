<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

/**
 * Raised when the employee_number presented at the scan endpoint does not
 * match any known employee. Mapped to 401 Unauthorized. Deliberately
 * distinct from InvalidQrTokenException so client apps can tell "wrong
 * device" apart from "wrong employee".
 */
class InvalidScanCredentialsException extends AttendanceModuleException
{
    public function statusCode(): int
    {
        return 401;
    }
}
