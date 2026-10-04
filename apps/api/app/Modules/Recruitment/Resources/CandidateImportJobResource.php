<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Resources;

use App\Models\CandidateImportJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateImportJob
 *
 * Exposes everything the uploader needs for the status panel — counts,
 * errors, timings — but NEVER the storage path. The repo is public, so
 * even the hint that an import file lived at some predictable path is
 * leaking too much.
 */
class CandidateImportJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_requirement_id' => $this->job_requirement_id,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'uploaded_filename' => $this->uploaded_filename,
            'total_rows' => (int) $this->total_rows,
            'created_candidates' => (int) $this->created_candidates,
            'reused_candidates' => (int) $this->reused_candidates,
            'created_applications' => (int) $this->created_applications,
            'skipped_duplicates' => (int) $this->skipped_duplicates,
            'status' => $this->status?->value,
            'errors' => $this->errors ?? [],
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
