<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\CandidateImportStatus;
use Database\Factories\CandidateImportJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistent progress + audit row for one bulk Candidate CSV/XLSX
 * import. One row per upload, lifecycle Pending → Processing → terminal.
 * See docs/recruitment/05-phase-2-plan.md §9 for the field semantics
 * and dedup contract the worker honours when filling the counters.
 */
class CandidateImportJob extends Model
{
    /** @use HasFactory<CandidateImportJobFactory> */
    use HasFactory;

    protected $fillable = [
        'job_requirement_id',
        'uploaded_by_user_id',
        'uploaded_filename',
        'storage_path',
        'total_rows',
        'created_candidates',
        'reused_candidates',
        'created_applications',
        'skipped_duplicates',
        'status',
        'errors',
        'started_at',
        'completed_at',
    ];

    /**
     * Storage path is kept hidden on serialisation — the repo is public
     * (per CLAUDE.md), and the stored path can carry the uploader's
     * identifier. The resource class is the primary gate; this is a
     * belt-and-suspenders second line for `->toJson()` escapes.
     *
     * @var list<string>
     */
    protected $hidden = [
        'storage_path',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'total_rows' => 0,
        'created_candidates' => 0,
        'reused_candidates' => 0,
        'created_applications' => 0,
        'skipped_duplicates' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CandidateImportStatus::class,
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_rows' => 'integer',
            'created_candidates' => 'integer',
            'reused_candidates' => 'integer',
            'created_applications' => 'integer',
            'skipped_duplicates' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobRequirement, $this>
     */
    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
