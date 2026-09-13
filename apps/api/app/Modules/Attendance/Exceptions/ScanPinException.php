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
        return new self('تم إيقاف المسح لهذا الرقم مؤقتاً بسبب محاولات خاطئة متكررة. حاول بعد 15 دقيقة.', 429);
    }

    public static function notIssued(): self
    {
        return new self('لم يُصدر لك رمز حضور بعد. تواصل مع الإدارة.', 422);
    }

    /**
     * Shared by "unknown employee number" and "wrong PIN" so the response
     * never confirms which half of the pair was wrong.
     */
    public static function invalidCredentials(): self
    {
        return new self('الرقم الوظيفي أو رمز الحضور غير صحيح.', 422);
    }

    public function statusCode(): int
    {
        return $this->status;
    }
}
