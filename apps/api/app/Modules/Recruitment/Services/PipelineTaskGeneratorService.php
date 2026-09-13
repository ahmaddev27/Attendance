<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\JobRequirement;
use App\Models\RecruitmentPipelineStage;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Events\JobRequirementStageAdvanced;
use App\Shared\Enums\StageOwnerRule;
use App\Shared\Enums\TaskEntityType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The heart of the Pipeline Engine.
 *
 * Listens on JobRequirementStageAdvanced (fired by
 * JobRequirementService::create() and ::advanceStage()) and, when the
 * newly entered stage carries auto_generate_task = true, mints a Task
 * row assigned to whoever the stage's owner_rule resolves to.
 *
 * Deliberately writes the Task directly (Task::create) rather than
 * calling TaskService::create — TaskService's non-admin authorisation
 * checks (must-be-teammate, created_by-required) are the wrong contract
 * for a system-generated handoff. We still mirror TaskService's
 * post-write pattern:
 *   - stamp a default status (first sort_order) and priority
 *   - fire nothing to the listener chain that TaskService uses (no
 *     TaskCreated event) because those listeners are user-audit
 *     oriented; instead we call NotificationService directly
 *
 * Everything is wrapped in try/catch — a missing status seed or a
 * broken notifier must NOT roll back the stage advance itself, which
 * has already committed.
 */
class PipelineTaskGeneratorService
{
    public function __construct(
        private readonly NotificationService $notifier,
    ) {}

    public function handle(JobRequirementStageAdvanced $event): void
    {
        try {
            $this->generate($event->job, $event->toStage, $event->fromStage);
        } catch (\Throwable $e) {
            // Never let a task-generation failure bubble up — the stage
            // has already advanced; surfacing this here would appear to
            // the caller as if the advance itself failed. Log for the
            // ops team; the manual UI still lets a human create the
            // task if needed.
            Log::warning('PipelineTaskGenerator failed', [
                'job_requirement_id' => $event->job->id,
                'stage_id' => $event->toStage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generate(JobRequirement $job, RecruitmentPipelineStage $stage, ?RecruitmentPipelineStage $fromStage): void
    {
        if (! $stage->auto_generate_task || $stage->is_terminal) {
            return;
        }

        $owner = $this->resolveOwner($job, $stage, $fromStage);

        // No user resolved — most often a role rule where nobody carries
        // the permission yet. Nothing to notify, nothing to assign.
        if (! $owner instanceof User) {
            Log::info('PipelineTaskGenerator: no owner resolved', [
                'job_requirement_id' => $job->id,
                'stage_id' => $stage->id,
                'rule_type' => $stage->owner_rule_type?->value,
                'rule_value' => $stage->owner_rule_value,
            ]);

            // Still surface the "stage advanced" event to the case
            // owner so a human can pick it up.
            $caseOwner = $job->recruitmentCase?->owner;
            if ($caseOwner instanceof User) {
                $this->notifier->jobStageAdvanced($job, $stage, $caseOwner);
            }

            return;
        }

        // The Task row itself. Only created when the resolved owner is
        // linked to an Employee (Task.assigned_to is a FK to employees).
        // Even without an Employee, we still notify the User directly.
        $employeeId = $owner->employee_id;

        if ($employeeId !== null) {
            $task = $this->createTask($job, $stage, $employeeId);
        } else {
            $task = null;
            Log::info('PipelineTaskGenerator: owner has no linked employee — skipping task, notifying directly', [
                'job_requirement_id' => $job->id,
                'stage_id' => $stage->id,
                'user_id' => $owner->id,
            ]);
        }

        // Always notify — either the task-assigned notification (routes
        // via the task's assignee.user) OR the stage-advanced fallback.
        // The dedup window in NotificationService prevents duplicates
        // when both fire.
        if ($task !== null) {
            $this->notifier->taskAssigned($task->fresh(['assignee.user']));
        }

        $this->notifier->jobStageAdvanced($job, $stage, $owner);
    }

    /**
     * Fills in the Task row from the stage's template + the job
     * context. Every column has a fallback so a stage config missing
     * the optional bits still produces a valid task.
     */
    private function createTask(JobRequirement $job, RecruitmentPipelineStage $stage, int $employeeId): ?Task
    {
        return DB::transaction(function () use ($job, $stage, $employeeId): ?Task {
            $statusId = TaskStatus::query()->orderBy('sort_order')->value('id');
            $priorityId = $this->resolvePriorityId($stage->task_priority);

            if ($statusId === null || $priorityId === null) {
                // Task tables haven't been seeded — treat as no-op
                // rather than crashing the caller.
                return null;
            }

            $title = $this->renderTitle($stage, $job);

            return Task::query()->create([
                'title' => $title,
                'description' => null,
                'status_id' => $statusId,
                'priority_id' => $priorityId,
                'created_by' => null,           // system-generated
                'assigned_to' => $employeeId,
                'entity_type' => TaskEntityType::JobRequirement->value,
                'entity_id' => $job->id,
                'due_date' => $this->calcDueDate($stage),
            ]);
        });
    }

    private function renderTitle(RecruitmentPipelineStage $stage, JobRequirement $job): string
    {
        $template = $stage->task_title_template;

        if ($template === null || $template === '') {
            return sprintf('%s — %s', $stage->name, $job->title);
        }

        return strtr($template, [
            '{job.title}' => $job->title,
            '{job.number}' => $job->job_number,
            '{stage.name}' => $stage->name,
        ]);
    }

    private function calcDueDate(RecruitmentPipelineStage $stage): ?\Illuminate\Support\Carbon
    {
        $sla = $stage->sla_hours;

        if ($sla === null || $sla <= 0) {
            return null;
        }

        // due_date is a DATE column — round up so an SLA of 4h still
        // shows a plausible due day on the assignee's board.
        return now()->addHours((int) $sla)->startOfDay();
    }

    private function resolvePriorityId(?string $priorityCode): ?int
    {
        if ($priorityCode !== null && $priorityCode !== '') {
            $id = TaskPriority::query()->where('code', $priorityCode)->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        // Fall back to whichever priority is seeded first (lowest
        // sort_order) — never trust that "normal" exists.
        $fallback = TaskPriority::query()->orderBy('sort_order')->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    private function resolveOwner(JobRequirement $job, RecruitmentPipelineStage $stage, ?RecruitmentPipelineStage $fromStage): ?User
    {
        $rule = $stage->owner_rule_type;
        $value = $stage->owner_rule_value;

        return match ($rule) {
            StageOwnerRule::None => null,
            StageOwnerRule::JobOwner => $job->owner,
            StageOwnerRule::CaseOwner => $job->recruitmentCase?->owner,
            StageOwnerRule::Specific => $value !== null && ctype_digit($value)
                ? User::query()->find((int) $value)
                : null,
            StageOwnerRule::Role => $value !== null && $value !== ''
                ? User::query()->permission($value)->first()
                : null,
            StageOwnerRule::PreviousStageOwner => $this->resolvePreviousStageOwner($job, $fromStage),
            default => null,
        };
    }

    /**
     * Finds the assignee of the most recently created auto-task on the
     * previous stage. Used by "shortlist" (owned by whoever ran
     * screening), etc. Falls back to job owner if no prior task exists
     * (e.g. stage was hand-cranked without auto-generation).
     */
    private function resolvePreviousStageOwner(JobRequirement $job, ?RecruitmentPipelineStage $fromStage): ?User
    {
        if ($fromStage === null) {
            return $job->owner;
        }

        $lastTask = Task::query()
            ->where('entity_type', TaskEntityType::JobRequirement->value)
            ->where('entity_id', $job->id)
            ->orderByDesc('created_at')
            ->first();

        if ($lastTask === null || $lastTask->assigned_to === null) {
            return $job->owner;
        }

        $employee = $lastTask->assignee;

        // Employee → User: use the reverse relation via users.employee_id
        return $employee !== null
            ? User::query()->where('employee_id', $employee->id)->first()
            : $job->owner;
    }
}
