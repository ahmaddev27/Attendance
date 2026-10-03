<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\CandidateApplication;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a CandidateApplication is created (manual attach or CSV
 * import). Listeners spawn the screening task and notify the job owner.
 * `?User $actor` is nullable so background jobs (import) can fire the
 * event without inventing a user.
 */
class CandidateApplied
{
    use Dispatchable;

    public function __construct(
        public readonly CandidateApplication $application,
        public readonly ?User $actor = null,
    ) {}
}
