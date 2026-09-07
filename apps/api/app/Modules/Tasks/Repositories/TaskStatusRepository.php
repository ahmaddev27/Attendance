<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Repositories;

use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Collection;

class TaskStatusRepository
{
    /**
     * @return Collection<int, TaskStatus>
     */
    public function all(): Collection
    {
        return TaskStatus::query()->ordered()->get();
    }

    public function findOrFail(int $id): TaskStatus
    {
        return TaskStatus::query()->findOrFail($id);
    }

    public function firstDoneState(): ?TaskStatus
    {
        return TaskStatus::query()->ordered()->where('is_done_state', true)->first();
    }

    public function firstBySortOrder(): ?TaskStatus
    {
        return TaskStatus::query()->ordered()->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TaskStatus
    {
        return TaskStatus::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TaskStatus $status, array $data): TaskStatus
    {
        $status->update($data);

        return $status->refresh();
    }

    public function delete(TaskStatus $status): void
    {
        $status->delete();
    }
}
