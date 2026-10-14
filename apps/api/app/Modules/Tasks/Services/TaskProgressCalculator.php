<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\Task;

/**
 * Derives a task's progress_percent from the completion state of its
 * subtasks, so admins never have to type it by hand for parent tasks.
 *
 * Rules:
 *  - With subtasks: done / (total - cancelled) * 100. Cancelled subtasks
 *    are "removed" from the denominator; if everything is cancelled there
 *    is nothing left to finish, so the parent is 100.
 *  - Without subtasks: a done-state status means 100; otherwise the stored
 *    value is kept (a manual entry, or the last value for a cancelled
 *    task — we deliberately do not wipe history by zeroing it).
 *
 * Subtask `progress_percent` is NOT averaged: the owner's mental model is
 * "how many subtasks finished vs remain".
 */
class TaskProgressCalculator
{
    public function compute(Task $task): int
    {
        $task->loadMissing('status', 'subtasks.status');

        if ($task->subtasks->isEmpty()) {
            return $this->leafProgress($task);
        }

        $cancelled = $task->subtasks->filter(fn (Task $s) => (bool) $s->status?->is_cancelled_state)->count();
        $completed = $task->subtasks->filter(fn (Task $s) => (bool) $s->status?->is_done_state)->count();
        $remaining = $task->subtasks->count() - $cancelled;

        if ($remaining <= 0) {
            return 100;
        }

        return $this->clamp((int) round($completed / $remaining * 100));
    }

    private function leafProgress(Task $task): int
    {
        if ($task->status?->is_done_state) {
            return 100;
        }

        return $this->clamp((int) $task->progress_percent);
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
