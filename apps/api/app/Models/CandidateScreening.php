<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One screening scorecard per CandidateApplication (1:1). The schema the
 * `scorecard` JSON is validated against lives on the stage itself —
 * `recruitment_pipeline_stages.screening_schema` — so admins can edit
 * the fields without a migration. Re-screens overwrite this row.
 */
class CandidateScreening extends Model
{
    use LogsActivity;

    protected $fillable = [
        'application_id',
        'scored_by_user_id',
        'scorecard',
        'overall_score',
        'passed',
        'recommendation',
        'notes',
        'scored_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'passed' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scorecard' => 'array',
            'overall_score' => 'decimal:2',
            'passed' => 'boolean',
            'scored_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CandidateApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recruitment.candidate_screening');
    }
}
