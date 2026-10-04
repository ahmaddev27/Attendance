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
 * Routes a "submit scorecard" task to each interviewer expected on the
 * panel. Phase 2 Week 3 scope: a single task to the interview's
 * created_by user — the explicit panelist assignment table that
 * supports full fan-out (one task per interviewer) is Phase 3.
 */
final class RouteInterviewFeedbackRequest
{
    public function handle(InterviewScheduled $event): void
    {
        try {
            $this->route($event);
        } catch (\Throwable $e) {
            Log::warning('RouteInterviewFeedbackRequest failed', [
                'interview_id' => $event->interview->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function route(InterviewScheduled $event): void
    {
        $interview = $event->interview;
        $creator = $interview->createdBy;

        if (! $creator instanceof User) {
            return;
        }

        $employeeId = $creator->employee_id;
        if ($employeeId === null) {
            return;
        }

        $statusId = TaskStatus::query()->orderBy('sort_order')->value('id');
        $priorityId = TaskPriority::query()->orderBy('sort_order')->value('id');
        if ($statusId === null || $priorityId === null) {
            return;
        }

        $candidateName = $interview->application?->candidate?->full_name ?? 'المرشّح';

        Task::query()->create([
            'title' => sprintf('تقديم تقييم مقابلة: %s (%s)', $candidateName, $interview->kind->value),
            'description' => sprintf('المقابلة #%s', $interview->interview_number),
            'status_id' => $statusId,
            'priority_id' => $priorityId,
            'created_by' => null,
            'assigned_to' => $employeeId,
            'entity_type' => TaskEntityType::Interview->value,
            'entity_id' => $interview->id,
            // Due by the time the interview starts — the scorecard
            // itself can be filled in after, but the panellist should
            // see the task on their board on or before the day.
            'due_date' => $interview->scheduled_at?->copy()->startOfDay(),
        ]);
    }
}
