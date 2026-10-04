<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

/**
 * Raised by ScanPinService when a public scan fails PIN verification. One
 * type with named constructors because the three failures share a caller
 * but map to different HTTP statuses.
 */
class ScanPinException extends AttendanceModuleException
{
    private function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function lockedOut(): self
    {
        return new self('تم إيقاف المسح لهذا الرقم مؤقتاً بسبب محاولات خاطئة متكررة. حاول بعد دقيقتين.', 429);
    }

    public static function notIssued(): self
    {
        return new self('لم يُصدر لك رمز حضور بعد. تواصل مع الإدارة.', 422);
    }

    /**
     * Deliberately generic so a wrong PIN cannot be distinguished from an
     * employee the attacker guessed at — enumeration of either side gets
     * the same response.
     */
    public static function invalidCredentials(): self
    {
        return new self('رمز الحضور غير صحيح.', 422);
    }

    public function statusCode(): int
    {
        return $this->status;
    }
}
