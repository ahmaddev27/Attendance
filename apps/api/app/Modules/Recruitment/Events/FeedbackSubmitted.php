<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\InterviewFeedback;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an InterviewFeedback is submitted or edited. The job
 * owner listener uses this to nudge the hiring manager that one more
 * panelist has weighed in — "all feedback in" fires InterviewCompleted
 * via the service, not from this event.
 */
class FeedbackSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly InterviewFeedback $feedback,
        public readonly User $actor,
    ) {}
}
