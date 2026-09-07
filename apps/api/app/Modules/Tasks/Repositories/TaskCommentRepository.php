<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Repositories;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Support\Collection;

class TaskCommentRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TaskComment
    {
        return TaskComment::query()->create($data)->load('user');
    }

    public function findOrFail(int $id): TaskComment
    {
        return TaskComment::query()->with('user')->findOrFail($id);
    }

    public function update(TaskComment $comment, string $body, ?array $mentions): TaskComment
    {
        $comment->update([
            'body' => $body,
            'mentions' => $mentions,
            'edited_at' => now(),
        ]);

        return $comment->refresh()->load('user');
    }

    public function delete(TaskComment $comment): void
    {
        $comment->delete();
    }

    /**
     * Every top-level comment for the task (newest first), each carrying
     * its full reply chain nested underneath — the "thread
     * reconstruction" the M6 spec asks for, built in a single query per
     * task rather than one query per comment.
     *
     * @return Collection<int, TaskComment>
     */
    public function threadForTask(Task $task): Collection
    {
        $all = TaskComment::query()
            ->where('task_id', $task->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $byParent = $all->groupBy('parent_id');

        $attachReplies = function (TaskComment $comment) use (&$attachReplies, $byParent) {
            $replies = $byParent->get($comment->id, new Collection())
                ->each($attachReplies);

            $comment->setRelation('replies', $replies);
        };

        return $byParent->get(null, new Collection())
            ->each($attachReplies)
            ->sortByDesc('created_at')
            ->values();
    }
}
