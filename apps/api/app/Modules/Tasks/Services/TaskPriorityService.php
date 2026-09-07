<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\TaskPriority;
use App\Modules\Tasks\Repositories\TaskPriorityRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TaskPriorityService
{
    public function __construct(
        private readonly TaskPriorityRepository $priorities,
    ) {}

    /**
     * @return Collection<int, TaskPriority>
     */
    public function all(): Collection
    {
        return $this->priorities->all();
    }

    public function find(int $id): TaskPriority
    {
        return $this->priorities->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TaskPriority
    {
        return $this->priorities->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TaskPriority $priority, array $data): TaskPriority
    {
        return $this->priorities->update($priority, $data);
    }

    /**
     * A priority already assigned to tasks cannot be removed outright —
     * the tasks.priority_id FK is restrictOnDelete, so this guard just
     * turns that into a clear 422 instead of a raw database constraint
     * error.
     */
    public function delete(TaskPriority $priority): void
    {
        if ($priority->tasks()->exists()) {
            throw ValidationException::withMessages([
                'task_priority' => 'This priority is assigned to existing tasks and cannot be deleted.',
            ]);
        }

        $this->priorities->delete($priority);
    }
}
