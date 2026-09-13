<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Repositories\LeadRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily sweep that surfaces every non-terminal Lead whose owner has not
 * logged contact within the configured number of days. The owner gets
 * an in-app toast so the pipeline is worked before rows fossilise into
 * the "Lost" bucket.
 *
 * `--days` defaults to 7 so the default cadence matches the product's
 * follow-up rhythm; ops can tune the window per environment without a
 * code change. Per-lead try/catch prevents one broken row (owner
 * deleted, missing company_name) from stopping the sweep.
 */
class ScanStaleLeads extends Command
{
    protected $signature = 'recruitment:scan-stale-leads
                            {--days=7 : how many days without contact qualifies as stale}';

    protected $description = 'Notify Lead owners for every active Lead not contacted in the past N days.';

    public function __construct(
        private readonly LeadRepository $leads,
        private readonly NotificationService $notifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days <= 0) {
            $this->error('--days must be a positive integer.');

            return self::INVALID;
        }

        $now = Carbon::now();
        $cutoff = $now->clone()->subDays($days);

        Log::info('recruitment:scan-stale-leads starting', [
            'at' => $now->toIso8601String(),
            'days' => $days,
        ]);

        $stale = $this->leads->staleActive($cutoff);

        $scanned = 0;
        $notifications = 0;

        foreach ($stale as $lead) {
            $scanned++;

            try {
                if ($this->notifyForLead($lead, $now, $days)) {
                    $notifications++;
                }
            } catch (\Throwable $e) {
                Log::warning('recruitment:scan-stale-leads failed for lead', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = sprintf('recruitment:scan-stale-leads done — scanned %d, sent %d notification(s)', $scanned, $notifications);
        Log::info($summary);
        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * Emit the "stale lead" notification to the lead's owner. Returns
     * whether a notification was actually attempted — a lead whose
     * owner user row has been removed is skipped, since there is no
     * recipient to reach.
     */
    private function notifyForLead(Lead $lead, Carbon $now, int $defaultDays): bool
    {
        $owner = $lead->owner;

        if (! $owner instanceof User) {
            return false;
        }

        $lastContact = $lead->last_contact_at;

        // Prefer the true elapsed span so the toast reads accurately —
        // fall back to the --days threshold when we never logged a first
        // contact so the message still has a concrete number.
        $daysSince = $lastContact !== null
            ? (int) floor($lastContact->diffInDays($now, false))
            : $defaultDays;

        if ($daysSince < 0) {
            $daysSince = $defaultDays;
        }

        $this->notifier->leadStale($lead, $owner, $daysSince);

        return true;
    }
}
