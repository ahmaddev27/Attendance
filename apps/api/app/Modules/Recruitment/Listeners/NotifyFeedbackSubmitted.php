<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Listeners;

use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Events\FeedbackSubmitted;
use Illuminate\Support\Facades\Log;

/**
 * Internal nudge for the job owner that a panellist has weighed in.
 * The notifier resolves the owner from the feedback → interview →
 * application → job chain; a missing link is a silent no-op (the
 * log keeps the trail for ops).
 */
final class NotifyFeedbackSubmitted
{
    public function __construct(private readonly NotificationService $notifier) {}

    public function handle(FeedbackSubmitted $event): void
    {
        try {
            $this->notifier->interviewFeedbackSubmitted($event->feedback);
        } catch (\Throwable $e) {
            Log::warning('interviewFeedbackSubmitted notifier failed', [
                'feedback_id' => $event->feedback->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
