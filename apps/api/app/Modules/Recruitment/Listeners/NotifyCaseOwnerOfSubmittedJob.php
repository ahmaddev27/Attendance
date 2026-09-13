<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Listeners;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Events\JobRequirementSubmitted;
use Illuminate\Support\Facades\Log;

/**
 * Tells the campaign (case) owner that a new job requirement landed
 * under their case. Skipped when the case owner is the one who created
 * the job — including the Convert flow, where the converting user
 * typically owns the freshly minted case.
 */
final class NotifyCaseOwnerOfSubmittedJob
{
    public function __construct(private readonly NotificationService $notifier) {}

    public function handle(JobRequirementSubmitted $event): void
    {
        $job = $event->job;
        $caseOwner = $job->recruitmentCase?->owner;

        if (! $caseOwner instanceof User) {
            return;
        }

        if ($event->actor instanceof User && $event->actor->id === $caseOwner->id) {
            return;
        }

        try {
            $this->notifier->jobRequirementSubmitted($job, $caseOwner);
        } catch (\Throwable $e) {
            Log::warning('jobRequirementSubmitted notifier failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
