<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Repositories;

use App\Models\TaskPriority;
use Illuminate\Database\Eloquent\Collection;

class TaskPriorityRepository
{
    /**
     * @return Collection<int, TaskPriority>
     */
    public function all(): Collection
    {
        return TaskPriority::query()->ordered()->get();
    }

    public function findOrFail(int $id): TaskPriority
    {
        return TaskPriority::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TaskPriority
    {
        return TaskPriority::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TaskPriority $priority, array $data): TaskPriority
    {
        $priority->update($data);

        return $priority->refresh();
    }

    public function delete(TaskPriority $priority): void
    {
        $priority->delete();
    }
}
