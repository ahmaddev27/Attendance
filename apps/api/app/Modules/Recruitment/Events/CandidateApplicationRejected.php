<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\CandidateApplication;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a CandidateApplication transitions to `rejected`. Carries
 * the rejecting user so audit + notifications can attribute the
 * decision without a second lookup.
 */
class CandidateApplicationRejected
{
    use Dispatchable;

    public function __construct(
        public readonly CandidateApplication $application,
        public readonly User $actor,
        public readonly string $reason,
    ) {}
}
