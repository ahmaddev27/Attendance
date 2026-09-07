<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

/**
 * Raised for check-in/check-out state conflicts (already checked in,
 * checked out without checking in, etc). Mapped to 409 Conflict — the
 * request is well-formed, but the current attendance state forbids it.
 */
class AttendanceException extends AttendanceModuleException
{
    public function statusCode(): int
    {
        return 409;
    }
}
