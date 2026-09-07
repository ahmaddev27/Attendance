<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Repositories;

use App\Models\TaskTag;
use Illuminate\Database\Eloquent\Collection;

class TaskTagRepository
{
    /**
     * @return Collection<int, TaskTag>
     */
    public function all(): Collection
    {
        return TaskTag::query()->orderBy('name')->get();
    }

    public function findOrFail(int $id): TaskTag
    {
        return TaskTag::query()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TaskTag
    {
        return TaskTag::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TaskTag $tag, array $data): TaskTag
    {
        $tag->update($data);

        return $tag->refresh();
    }

    public function delete(TaskTag $tag): void
    {
        $tag->delete();
    }
}
