<?php

declare(strict_types=1);

namespace App\Modules\Requests\Services;

use App\Models\Request as RequestModel;

/**
 * Generates the human-facing "REQ-0001" style identifier. Mirrors
 * App\Modules\Employees\Repositories\EmployeeRepository::maxEmployeeNumberForUpdate():
 * locking the current max `id` for update inside the caller's transaction
 * (see RequestService::submit()) serializes concurrent submissions so two
 * near-simultaneous requests can never be assigned the same number.
 *
 * `id` (not a parsed-out numeric suffix of the previous request_number) is
 * used as the sequence source because it is always a plain, portable,
 * monotonically increasing integer — extracting "the number after REQ-"
 * back out of a string column would need database-specific string
 * functions to stay race-safe across both MySQL (production) and SQLite
 * (the test suite).
 */
class RequestNumberGenerator
{
    private const PREFIX = 'REQ-';

    private const MIN_DIGITS = 4;

    /**
     * Must be called inside a transaction.
     */
    public function generate(): string
    {
        $nextSequence = ((int) RequestModel::query()->lockForUpdate()->max('id')) + 1;

        return self::PREFIX.str_pad((string) $nextSequence, self::MIN_DIGITS, '0', STR_PAD_LEFT);
    }
}
