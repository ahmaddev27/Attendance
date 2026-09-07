<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\TaskTag;
use App\Modules\Tasks\Repositories\TaskTagRepository;
use Illuminate\Database\Eloquent\Collection;

class TaskTagService
{
    public function __construct(
        private readonly TaskTagRepository $tags,
    ) {}

    /**
     * @return Collection<int, TaskTag>
     */
    public function all(): Collection
    {
        return $this->tags->all();
    }

    public function find(int $id): TaskTag
    {
        return $this->tags->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TaskTag
    {
        return $this->tags->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TaskTag $tag, array $data): TaskTag
    {
        return $this->tags->update($tag, $data);
    }

    /**
     * Detaching from every task first keeps a tag deletion from being
     * blocked by anything at the database level — task_tag_task rows
     * cascade on delete already, this just makes the intent explicit and
     * keeps the operation safe to call even before that cascade runs.
     */
    public function delete(TaskTag $tag): void
    {
        $tag->tasks()->detach();

        $this->tags->delete($tag);
    }
}
