<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JobRequirement;
use App\Models\Task;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Repositories\JobRequirementRepository;
use App\Shared\Enums\TaskEntityType;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly sweep that finds every open JobRequirement whose current stage
 * has been sitting longer than the stage's `sla_hours` budget and nudges
 * both the current stage owner AND the Case owner.
 *
 * "Current stage owner" is resolved the same way
 * PipelineTaskGeneratorService::resolveOwner does it — the assignee of
 * the most recent auto-generated task on this job, promoted back to the
 * linked User. Falling back to $job->owner when no such task exists
 * mirrors the generator's own fallback so the two sides agree on who
 * "owns" the current step.
 *
 * Dedup between the two recipients (owner === case owner) is handled by
 * NotificationService's per-recipient dedup window — this command just
 * asks for both and lets the notifier drop the duplicate broadcast. A
 * per-job try/catch guarantees one malformed job can't halt the sweep
 * of the rest of the queue.
 */
class ScanRecruitmentSla extends Command
{
    protected $signature = 'recruitment:scan-sla';

    protected $description = 'Notify stage and case owners for every JobRequirement whose current stage has breached its SLA.';

    public function __construct(
        private readonly JobRequirementRepository $jobs,
        private readonly NotificationService $notifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = Carbon::now();

        Log::info('recruitment:scan-sla starting', ['at' => $now->toIso8601String()]);

        $breached = $this->jobs->stagesBreachingSla($now);

        $scanned = 0;
        $notifications = 0;

        foreach ($breached as $job) {
            $scanned++;

            try {
                $notifications += $this->notifyForJob($job, $now);
            } catch (\Throwable $e) {
                // One bad job (missing relation, corrupt row) must not
                // stop the sweep — log for ops and move on so the rest
                // of the pipeline still gets nudged.
                Log::warning('recruitment:scan-sla failed for job', [
                    'job_requirement_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = sprintf('recruitment:scan-sla done — scanned %d, sent %d notification(s)', $scanned, $notifications);
        Log::info($summary);
        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * Emit the "stage overdue" notification to the resolved current
     * stage owner and to the Case owner. Returns the number of
     * NotificationService calls actually made — the caller uses it for
     * the run summary; the notifier itself decides whether the toast is
     * broadcast or just written to the durable inbox.
     */
    private function notifyForJob(JobRequirement $job, Carbon $now): int
    {
        $stage = $job->currentStage;

        if ($stage === null) {
            return 0;
        }

        $sla = $stage->sla_hours;
        $enteredAt = $job->stage_entered_at;

        if ($sla === null || $sla <= 0 || $enteredAt === null) {
            return 0;
        }

        $hoursOverdue = (int) floor($enteredAt->clone()->addHours((int) $sla)->diffInHours($now, false));

        if ($hoursOverdue < 0) {
            $hoursOverdue = 0;
        }

        $stageOwner = $this->resolveCurrentStageOwner($job);
        $caseOwner = $job->recruitmentCase?->owner;

        $recipients = [];

        if ($stageOwner instanceof User) {
            $recipients[$stageOwner->id] = $stageOwner;
        }

        if ($caseOwner instanceof User) {
            $recipients[$caseOwner->id] = $caseOwner;
        }

        foreach ($recipients as $recipient) {
            $this->notifier->stageSlaBreached($job, $stage, $hoursOverdue, $recipient);
        }

        return count($recipients);
    }

    /**
     * Mirrors PipelineTaskGeneratorService::resolvePreviousStageOwner —
     * the current stage owner is the assignee of the latest auto-task
     * on this job, promoted back to the User row. When no such task
     * exists (stage advanced manually, or seeder started the job past
     * the first stage) we fall through to the job owner.
     */
    private function resolveCurrentStageOwner(JobRequirement $job): ?User
    {
        $lastTask = Task::query()
            ->where('entity_type', TaskEntityType::JobRequirement->value)
            ->where('entity_id', $job->id)
            ->orderByDesc('created_at')
            ->first();

        if ($lastTask === null || $lastTask->assigned_to === null) {
            return $job->owner;
        }

        $employeeId = $lastTask->assigned_to;

        $user = User::query()->where('employee_id', $employeeId)->first();

        return $user ?? $job->owner;
    }
}
