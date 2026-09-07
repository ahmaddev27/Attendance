<?php

declare(strict_types=1);

namespace App\Modules\Requests\Services;

use App\Models\Request as RequestModel;

/**
 * Generates the human-facing "REQ-0001" style identifier. Locks the current
 * max sequence for update inside the caller's transaction (see
 * RequestService::submit()) so two near-simultaneous submissions can never
 * be assigned the same number.
 *
 * Sequence source: the numeric suffix parsed out of the existing
 * `request_number` column, NOT the row `id`. MySQL InnoDB's AUTO_INCREMENT
 * counter advances on every insert attempt and does NOT roll back on
 * failed transactions — the test suite's RefreshDatabase trait wraps each
 * test in a rolled-back transaction, so `max(id)` diverges from the actual
 * next id across tests and picks up drift like "REQ-0026 after a fresh
 * table". `request_number` is written and rolled back together with the
 * row, so parsing its suffix stays consistent.
 *
 * Portability: SUBSTRING(str, pos) and CAST(... AS UNSIGNED) work in both
 * MySQL 8 (production) and SQLite 3.31+ (the test fallback), so the
 * expression is safe on either driver.
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
        $suffixStartPos = strlen(self::PREFIX) + 1; // SUBSTRING is 1-indexed.

        $maxSequence = (int) RequestModel::query()
            ->lockForUpdate()
            ->selectRaw(
                'COALESCE(MAX(CAST(SUBSTRING(request_number, ?) AS UNSIGNED)), 0) as seq',
                [$suffixStartPos]
            )
            ->value('seq');

        return self::PREFIX.str_pad((string) ($maxSequence + 1), self::MIN_DIGITS, '0', STR_PAD_LEFT);
    }
}
