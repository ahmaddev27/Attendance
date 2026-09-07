<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\User;
use App\Shared\Enums\TaskAction;

/**
 * Appends rows to a task's insert-only audit trail. Every write goes
 * through here rather than `TaskHistory::create()` directly so the
 * "insert-only, always stamp created_at ourselves" rule (the model has
 * `$timestamps = false`) lives in exactly one place.
 */
class TaskHistoryService
{
    public function log(Task $task, User $user, TaskAction $action, ?array $oldValue = null, ?array $newValue = null): TaskHistory
    {
        return TaskHistory::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'created_at' => now(),
        ]);
    }
}
