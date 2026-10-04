<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CandidateApplication;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Shared\Enums\CandidateApplicationStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Hourly sweep that finds every open CandidateApplication whose
 * current stage has exceeded its `sla_hours` budget and nudges the
 * job owner. Mirror of ScanRecruitmentSla but on the per-application
 * stage pointer added in Phase 2.
 *
 * Dedup: we don't have a persistent `sla_breach_notified_at` column on
 * candidate_applications (that lands in Phase 3). For Phase 2 Week 3
 * we lean on Cache::remember with a 24h TTL — same wake-up cadence as
 * JobRequirement's own REPEAT_AFTER_HOURS = 24.
 */
class ScanApplicationSla extends Command
{
    protected $signature = 'recruitment:application-sla-scan';

    protected $description = 'Notify job owners for every CandidateApplication whose current stage has breached its SLA.';

    /** TTL for the per-application dedup entry, in seconds. */
    private const DEDUP_TTL = 24 * 60 * 60;

    public function __construct(
        private readonly NotificationService $notifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = Carbon::now();

        Log::info('recruitment:application-sla-scan starting', ['at' => $now->toIso8601String()]);

        $applications = CandidateApplication::query()
            ->with(['currentStage', 'jobRequirement.owner'])
            ->whereNotIn('status', [
                CandidateApplicationStatus::Rejected->value,
                CandidateApplicationStatus::Withdrawn->value,
                CandidateApplicationStatus::Hired->value,
            ])
            ->whereHas('currentStage', fn ($stage) => $stage->whereNotNull('sla_hours'))
            ->get();

        $scanned = 0;
        $notified = 0;

        foreach ($applications as $application) {
            $scanned++;
            try {
                if ($this->notifyIfBreached($application, $now)) {
                    $notified++;
                }
            } catch (\Throwable $e) {
                Log::warning('recruitment:application-sla-scan failed for application', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = sprintf(
            'recruitment:application-sla-scan done — scanned %d, notified %d',
            $scanned,
            $notified,
        );
        Log::info($summary);
        $this->info($summary);

        return self::SUCCESS;
    }

    private function notifyIfBreached(CandidateApplication $application, Carbon $now): bool
    {
        $stage = $application->currentStage;
        $sla = $stage?->sla_hours;
        $enteredAt = $application->stage_entered_at;

        if ($stage === null || $sla === null || $sla <= 0 || $enteredAt === null) {
            return false;
        }

        if ($enteredAt->clone()->addHours((int) $sla)->greaterThan($now)) {
            return false;
        }

        // Phase 2 Week 3: in-memory dedup via the cache. A persistent
        // column is a Phase 3 refinement — leaving the dedup here means
        // nothing new to migrate.
        $dedupKey = "application-sla-breach:{$application->id}:{$stage->id}";
        if (! Cache::add($dedupKey, 1, self::DEDUP_TTL)) {
            return false;
        }

        $owner = $application->jobRequirement?->owner;
        if (! $owner instanceof User) {
            return false;
        }

        $this->notifier->applicationStageAdvanced($application, $stage, $owner);

        return true;
    }
}
