<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\TaskStatus;
use App\Modules\Tasks\Repositories\TaskStatusRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TaskStatusService
{
    public function __construct(
        private readonly TaskStatusRepository $statuses,
    ) {}

    /**
     * @return Collection<int, TaskStatus>
     */
    public function all(): Collection
    {
        return $this->statuses->all();
    }

    public function find(int $id): TaskStatus
    {
        return $this->statuses->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TaskStatus
    {
        return $this->statuses->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TaskStatus $status, array $data): TaskStatus
    {
        return $this->statuses->update($status, $data);
    }

    /**
     * A status already assigned to tasks cannot be removed outright — the
     * tasks.status_id FK is restrictOnDelete, so this guard just turns
     * that into a clear 422 instead of a raw database constraint error.
     */
    public function delete(TaskStatus $status): void
    {
        if ($status->tasks()->exists()) {
            throw ValidationException::withMessages([
                'task_status' => 'This status is assigned to existing tasks and cannot be deleted.',
            ]);
        }

        $this->statuses->delete($status);
    }
}
