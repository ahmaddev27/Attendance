<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Events;

use App\Models\TaskComment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a comment has been persisted, so a later milestone can
 * listen for it and notify $comment->mentions (and the task's
 * assignee/creator) without TaskCommentService needing to know anything
 * about the notification channel.
 */
class CommentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TaskComment $comment,
    ) {}
}
