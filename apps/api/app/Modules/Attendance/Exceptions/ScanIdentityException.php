<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

/**
 * Raised when a scan carrying a bearer token cannot be tied to the
 * employee it claims to be for.
 */
class ScanIdentityException extends AttendanceModuleException
{
    private function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function invalidToken(): self
    {
        return new self('جلسة الدخول غير صالحة أو منتهية. سجّل الدخول مجدداً.', 401);
    }

    public static function accountNotLinked(): self
    {
        return new self('حسابك غير مرتبط بملف موظف.', 422);
    }

    public static function employeeMismatch(): self
    {
        return new self('لا يمكن تسجيل الحضور عن موظف آخر.', 403);
    }

    public function statusCode(): int
    {
        return $this->status;
    }
}
