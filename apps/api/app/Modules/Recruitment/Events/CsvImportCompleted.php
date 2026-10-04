<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\CandidateImportJob;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a bulk candidate import finishes (success, partial
 * success, or failure — the status on the row says which). Carries
 * the uploader explicitly so listeners that notify them don't have to
 * re-query the user row. Dispatched inside the Queue job's handle()
 * after the counters + status are persisted, so listeners see a
 * committed row.
 */
class CsvImportCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly CandidateImportJob $importJob,
        public readonly User $uploader,
    ) {}
}
