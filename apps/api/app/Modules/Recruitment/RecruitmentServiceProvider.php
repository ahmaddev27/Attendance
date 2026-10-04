<?php

declare(strict_types=1);

namespace App\Modules\Recruitment;

use App\Modules\Recruitment\Events\FeedbackSubmitted;
use App\Modules\Recruitment\Events\InterviewScheduled;
use App\Modules\Recruitment\Events\JobRequirementStageAdvanced;
use App\Modules\Recruitment\Events\JobRequirementSubmitted;
use App\Modules\Recruitment\Events\LeadCreated;
use App\Modules\Recruitment\Listeners\GenerateInterviewSchedulingTask;
use App\Modules\Recruitment\Listeners\NotifyCaseOwnerOfSubmittedJob;
use App\Modules\Recruitment\Listeners\NotifyFeedbackSubmitted;
use App\Modules\Recruitment\Listeners\NotifyInterviewScheduled;
use App\Modules\Recruitment\Listeners\NotifyLeadOwnerOfNewLead;
use App\Modules\Recruitment\Listeners\RouteInterviewFeedbackRequest;
use App\Modules\Recruitment\Services\PipelineTaskGeneratorService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Pipeline Engine's one non-obvious edge: whenever a job
 * enters a new stage, the PipelineTaskGenerator gets a chance to spawn
 * the auto-task for the next owner. Kept as a dedicated provider (as
 * opposed to inline in AppServiceProvider) so the Recruitment module
 * stays self-contained — pulling the provider out of
 * bootstrap/providers.php disables every bit of Recruitment automation
 * without touching anything else.
 *
 * Audit logging for Lead / Client / RecruitmentCase / JobRequirement is
 * handled at the model layer via Spatie's LogsActivity trait rather
 * than dedicated Observers — every create/update/delete already flows
 * through Eloquent events the trait hooks into, and the existing
 * Reports\AuditLogController reads the same activity_log table for
 * free. No boot-time registration is needed.
 */
class RecruitmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            JobRequirementStageAdvanced::class,
            [PipelineTaskGeneratorService::class, 'handle'],
        );

        Event::listen(LeadCreated::class, NotifyLeadOwnerOfNewLead::class);
        Event::listen(JobRequirementSubmitted::class, NotifyCaseOwnerOfSubmittedJob::class);

        // Phase 2 Week 3 — Interview + Feedback automation. All three
        // InterviewScheduled listeners run in-process; a listener's
        // failure is contained inside its own try/catch so one bad
        // notifier can't abort the task-generation or vice versa.
        Event::listen(InterviewScheduled::class, GenerateInterviewSchedulingTask::class);
        Event::listen(InterviewScheduled::class, RouteInterviewFeedbackRequest::class);
        Event::listen(InterviewScheduled::class, NotifyInterviewScheduled::class);
        Event::listen(FeedbackSubmitted::class, NotifyFeedbackSubmitted::class);
    }
}
