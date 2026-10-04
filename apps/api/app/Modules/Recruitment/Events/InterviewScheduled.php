<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\Interview;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an Interview row is written (initial schedule, reschedule
 * mint of the replacement row). Listeners spawn the "fill feedback"
 * task per interviewer and notify the creator + job owner.
 */
class InterviewScheduled
{
    use Dispatchable;

    public function __construct(
        public readonly Interview $interview,
        public readonly User $actor,
    ) {}
}
