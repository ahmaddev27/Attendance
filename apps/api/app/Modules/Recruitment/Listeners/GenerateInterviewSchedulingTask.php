<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Listeners;

use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Modules\Recruitment\Events\InterviewScheduled;
use App\Shared\Enums\TaskEntityType;
use Illuminate\Support\Facades\Log;

/**
 * Materialises a "coordinate the interview" task on the application
 * when a brand-new interview is scheduled — a nudge for the
 * coordinator to confirm the room/link/reminders.
 *
 * Phase 2 Week 3 scope: the real "application entered the
 * `interviewing` stage" trigger belongs on
 * CandidateApplicationService::advanceStage(), which lands in a later
 * slice. Until then we hook off InterviewScheduled directly so the
 * handoff still happens — the task's entity_type still points at the
 * CandidateApplication, so the Phase 3 trigger will drop in without
 * breaking the task feed.
 */
final class GenerateInterviewSchedulingTask
{
    public function handle(InterviewScheduled $event): void
    {
        try {
            $this->generate($event);
        } catch (\Throwable $e) {
            // The interview is already committed — a task-generation
            // failure must never surface to the caller as a scheduling
            // error. Log for ops; the admin UI still allows a human to
            // create the follow-up task by hand.
            Log::warning('GenerateInterviewSchedulingTask failed', [
                'interview_id' => $event->interview->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generate(InterviewScheduled $event): void
    {
        $interview = $event->interview;
        $owner = $event->actor;

        if (! $owner instanceof User) {
            return;
        }

        $employeeId = $owner->employee_id;
        if ($employeeId === null) {
            return;
        }

        $statusId = TaskStatus::query()->orderBy('sort_order')->value('id');
        $priorityId = TaskPriority::query()->orderBy('sort_order')->value('id');
        if ($statusId === null || $priorityId === null) {
            return;
        }

        $candidateName = $interview->application?->candidate?->full_name ?? 'المرشّح';
        $jobTitle = $interview->application?->jobRequirement?->title ?? '';

        Task::query()->create([
            'title' => sprintf('تنسيق مقابلة: %s — %s', $candidateName, $jobTitle),
            'description' => sprintf(
                'المقابلة #%s في %s',
                $interview->interview_number,
                $interview->scheduled_at?->toDateTimeString() ?? '',
            ),
            'status_id' => $statusId,
            'priority_id' => $priorityId,
            'created_by' => null,
            'assigned_to' => $employeeId,
            'entity_type' => TaskEntityType::CandidateApplication->value,
            'entity_id' => $interview->application_id,
            'due_date' => $interview->scheduled_at?->copy()->startOfDay(),
        ]);
    }
}
