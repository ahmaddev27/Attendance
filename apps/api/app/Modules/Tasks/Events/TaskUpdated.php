<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after any update to a task's fields (including status transitions
 * and completion). See TaskCreated for why this has no listeners yet.
 *
 * @property array<string, array{old: mixed, new: mixed}> $changes  field => [old, new] for every changed, tracked field
 */
class TaskUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function __construct(
        public readonly Task $task,
        public readonly array $changes = [],
    ) {}
}
