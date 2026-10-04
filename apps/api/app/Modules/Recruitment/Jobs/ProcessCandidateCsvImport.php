<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Jobs;

use App\Models\CandidateImportJob;
use App\Modules\Recruitment\Services\CandidateImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued worker for a CandidateImportJob row. Carries only the row id
 * (not the model instance) so the job payload stays small and the
 * worker always reads the freshest status before mutating. All real
 * work lives on CandidateImportService::process() — the job class is
 * the thin bus adapter so the service stays unit-testable without
 * dragging the queue in.
 */
final class ProcessCandidateCsvImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * One retry max — the file-level operations are idempotent (storage
     * read + row-counter writes) but a Processing row that got halfway
     * before the worker died would double-count on a blind retry. The
     * fail path flips the row to Failed and surfaces to the uploader.
     */
    public int $tries = 1;

    public function __construct(public readonly int $importJobId) {}

    public function handle(CandidateImportService $service): void
    {
        $importJob = CandidateImportJob::query()->find($this->importJobId);

        if ($importJob === null) {
            // Row was deleted between dispatch and worker pickup —
            // nothing to do, treat as a no-op so the queue doesn't retry.
            return;
        }

        $service->process($importJob);
    }
}
