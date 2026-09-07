<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

/**
 * Base type for every business-rule failure raised by the Attendance
 * module's services. ScanController catches this single type and maps it
 * to the correct HTTP status via statusCode(), instead of duplicating
 * try/catch blocks per exception subtype.
 */
abstract class AttendanceModuleException extends \RuntimeException
{
    abstract public function statusCode(): int;
}
