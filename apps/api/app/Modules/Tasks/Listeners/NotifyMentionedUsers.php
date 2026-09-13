<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Listeners;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tasks\Events\CommentCreated;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifies everyone named in a new task comment. The comment is already
 * committed when this runs, so a notifier outage is logged and never shows up
 * as a failed comment.
 */
final class NotifyMentionedUsers
{
    public function __construct(private readonly NotificationService $notifier) {}

    public function handle(CommentCreated $event): void
    {
        $comment = $event->comment;

        $mentionedIds = array_values(array_diff(
            array_map('intval', $comment->mentions ?? []),
            [(int) $comment->user_id],
        ));

        if ($mentionedIds === []) {
            return;
        }

        $comment->loadMissing(['task', 'user']);

        $recipients = User::query()
            ->whereIn('id', $mentionedIds)
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $recipient) {
            try {
                $this->notifier->mentionedInComment($comment, $recipient);
            } catch (Throwable $e) {
                Log::warning('mentionedInComment notifier failed', [
                    'comment_id' => $comment->id,
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
