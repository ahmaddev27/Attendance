<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `php artisan taqat:prune-old-rows` — retention sweeper for high-volume
 * log tables that would otherwise grow unbounded.
 *
 * Kept as a plain command (raw DELETEs) instead of MassPrunable on each
 * Model so the surface of the change stays inside the OPS wave and does
 * not require editing Model files owned by other feature waves.
 *
 * Cutoffs, one-liner per table:
 *   - notifications : read for > 90 days     → the user has moved on
 *   - sms_logs      : created > 90 days      → keep 3 months for audit
 *   - whatsapp_logs : created > 90 days      → keep 3 months for audit
 *   - push_tokens   : last_used > 60 days    → the device is gone
 *
 * Every DELETE runs in a chunked loop so we never lock the table for a
 * multi-million-row sweep, and so a run interrupted mid-way just resumes
 * on the next daily invocation. `Schema::hasTable` guards each block so
 * the command is safe to schedule before every migration has run in a
 * newly bootstrapped environment.
 */
class TaqatPruneOldRows extends Command
{
    protected $signature = 'taqat:prune-old-rows
        {--chunk=1000 : Rows deleted per statement to avoid long table locks}
        {--dry-run : Report counts without deleting anything}';

    protected $description = 'Prune old notifications, SMS/WhatsApp logs and stale push tokens';

    /**
     * Safety cap — a single invocation deletes at most this many rows per
     * table. Prevents a first-ever run from spending the whole night
     * hammering the DB; steady-state days delete far fewer than this.
     */
    private const MAX_PER_TABLE = 500_000;

    public function handle(): int
    {
        $chunk = max(100, (int) $this->option('chunk'));
        $dry = (bool) $this->option('dry-run');

        $now = now();

        $summary = [
            ['notifications', $this->pruneNotifications($now->copy()->subDays(90), $chunk, $dry)],
            ['sms_logs',      $this->pruneByCreatedAt('sms_logs', $now->copy()->subDays(90), $chunk, $dry)],
            ['whatsapp_logs', $this->pruneByCreatedAt('whatsapp_logs', $now->copy()->subDays(90), $chunk, $dry)],
            ['push_tokens',   $this->prunePushTokens($now->copy()->subDays(60), $chunk, $dry)],
        ];

        $this->table(
            ['table', $dry ? 'would_delete' : 'deleted'],
            $summary,
        );

        return self::SUCCESS;
    }

    /**
     * `notifications` (Laravel's DatabaseNotification table): drop rows the
     * user has already read AND whose read_at is older than the cutoff.
     * Unread rows are kept forever — the user still needs to see them.
     */
    private function pruneNotifications(\DateTimeInterface $cutoff, int $chunk, bool $dry): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        if ($dry) {
            return (int) DB::table('notifications')
                ->whereNotNull('read_at')
                ->where('read_at', '<', $cutoff)
                ->count();
        }

        return $this->chunkedDelete(
            fn () => DB::table('notifications')
                ->whereNotNull('read_at')
                ->where('read_at', '<', $cutoff)
                ->limit($chunk)
                ->delete(),
        );
    }

    /**
     * Simple created_at cutoff — sms_logs / whatsapp_logs.
     */
    private function pruneByCreatedAt(string $table, \DateTimeInterface $cutoff, int $chunk, bool $dry): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        if ($dry) {
            return (int) DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->count();
        }

        return $this->chunkedDelete(
            fn () => DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->delete(),
        );
    }

    /**
     * push_tokens has a nullable last_used_at — treat NULL as never-used
     * and fall back to created_at so a stale registration that never
     * fired a real push still ages out.
     */
    private function prunePushTokens(\DateTimeInterface $cutoff, int $chunk, bool $dry): int
    {
        if (! Schema::hasTable('push_tokens')) {
            return 0;
        }

        $query = fn () => DB::table('push_tokens')
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_used_at', '<', $cutoff)
                    ->orWhere(function ($inner) use ($cutoff): void {
                        $inner->whereNull('last_used_at')
                            ->where('created_at', '<', $cutoff);
                    });
            });

        if ($dry) {
            return (int) $query()->count();
        }

        return $this->chunkedDelete(
            fn () => $query()->limit($chunk)->delete(),
        );
    }

    /**
     * Repeat the delete until it returns 0 (or we hit the per-table cap).
     * Runs each chunk in its own transaction so a mid-loop failure does
     * not roll back rows already pruned this pass.
     */
    private function chunkedDelete(\Closure $deleteChunk): int
    {
        $total = 0;

        do {
            $deleted = (int) $deleteChunk();
            $total += $deleted;

            if ($total >= self::MAX_PER_TABLE) {
                break;
            }
        } while ($deleted > 0);

        return $total;
    }
}
