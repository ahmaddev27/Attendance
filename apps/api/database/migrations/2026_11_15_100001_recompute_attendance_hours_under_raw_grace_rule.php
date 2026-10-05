<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Backfill: replay WorkingHoursCalculator across the last 60 days of
 * attendance rows under the new rule set shipped 2026-10-05 (raw late
 * / early-leave minutes, grace governs the status label only, every
 * timestamp anchored to Asia/Gaza).
 *
 * Historical rows were persisted by the OLD rule (grace shrunk the
 * reported minutes), so without this backfill a row that was late by
 * 55 minutes under a 15-minute grace would keep displaying 40 forever
 * until the admin edits its check_in_at. 60 days is wide enough to
 * cover the current and previous month, which is what the admin UI
 * surfaces today.
 *
 * The command itself is idempotent: it only writes rows whose
 * recomputed values differ from the stored ones, so re-running
 * `migrate --force` after this migration already landed is a no-op.
 *
 * down() is intentionally empty — reversing the backfill would mean
 * re-applying the OLD rule to rows that are now correct, which is
 * worse than keeping them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $to = now('Asia/Gaza')->toDateString();
        $from = now('Asia/Gaza')->subDays(60)->toDateString();

        Log::info('migration recompute-attendance-hours starting', [
            'from' => $from,
            'to' => $to,
        ]);

        // Call directly so the migration output is streamed back to the
        // deploy log — the operator sees the "scanned N / changed M"
        // summary alongside the usual "Migrated: ..." line.
        Artisan::call('attendance:recompute-hours', [
            '--from' => $from,
            '--to' => $to,
        ]);
    }

    public function down(): void
    {
        // No-op: see docblock above.
    }
};
