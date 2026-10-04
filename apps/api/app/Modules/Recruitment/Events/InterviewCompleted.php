<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\Interview;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by InterviewService::complete() once ALL expected feedbacks for
 * the interview have landed — the signal that the application is ready
 * to advance into `client_review`. Dispatching early (before every
 * interviewer has submitted) would push the application forward with a
 * partial picture, which is exactly what the manual-decision rule
 * (owner Q5) forbids.
 */
class InterviewCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Interview $interview,
    ) {}
}
