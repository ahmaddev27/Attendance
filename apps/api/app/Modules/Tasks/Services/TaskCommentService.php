<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Modules\Tasks\Events\CommentCreated;
use App\Modules\Tasks\Repositories\TaskCommentRepository;
use App\Shared\Enums\TaskAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The frontend resolves @mention tokens to concrete user_ids before a
 * comment is ever submitted, so `mentions` arrives here as a plain array
 * of ids — no regex extraction happens server-side.
 */
class TaskCommentService
{
    public function __construct(
        private readonly TaskCommentRepository $comments,
        private readonly TaskHistoryService $history,
    ) {}

    /**
     * @param  array<int>|null  $mentions
     */
    public function create(Task $task, User $user, string $body, ?array $mentions = null, ?int $parentId = null): TaskComment
    {
        $comment = DB::transaction(function () use ($task, $user, $body, $mentions, $parentId) {
            $comment = $this->comments->create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'parent_id' => $parentId,
                'body' => $body,
                'mentions' => $mentions,
            ]);

            $this->history->log($task, $user, TaskAction::Commented, null, ['comment_id' => $comment->id]);

            return $comment;
        });

        CommentCreated::dispatch($comment);

        return $comment;
    }

    /**
     * @param  array<int>|null  $mentions
     */
    public function update(TaskComment $comment, User $user, string $body, ?array $mentions = null): TaskComment
    {
        $this->assertOwnedBy($comment, $user);

        return $this->comments->update($comment, $body, $mentions);
    }

    public function delete(TaskComment $comment, User $user): void
    {
        $this->assertOwnedBy($comment, $user);

        $this->comments->delete($comment);
    }

    /**
     * @return Collection<int, TaskComment>
     */
    public function threadForTask(Task $task): Collection
    {
        return $this->comments->threadForTask($task);
    }

    private function assertOwnedBy(TaskComment $comment, User $user): void
    {
        if ($comment->user_id !== $user->id) {
            throw new AuthorizationException('You may only modify your own comments.');
        }
    }
}
