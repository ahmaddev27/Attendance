<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a task has been persisted. No listeners yet — this exists so
 * the notifications milestone can hang an "assignee got notified" listener
 * off of it without touching TaskService again.
 */
class TaskCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Task $task,
    ) {}
}
