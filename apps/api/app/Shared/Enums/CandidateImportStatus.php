<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Lifecycle state of a bulk candidate CSV/XLSX import. The row is
 * created by the controller as `Pending`, flipped to `Processing` the
 * moment the queue worker picks it up, and ends on one of the terminal
 * states so the uploader's status endpoint tells a complete story.
 *
 * `CompletedWithErrors` is used both when every row succeeded (errors
 * array is empty) and when some rows were skipped — the UI reads
 * `errors` + the counters to decide which badge to render. `Failed`
 * is reserved for catastrophic failures (file unreadable, schema
 * mismatch) where NO row could be processed.
 */
enum CandidateImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    /**
     * True when the import has finished — the worker won't touch the
     * row again, so the status endpoint can stop polling.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::CompletedWithErrors, self::Failed], true);
    }
}
