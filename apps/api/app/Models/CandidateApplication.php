<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\CandidateApplicationStatus;
use App\Shared\Enums\TaskEntityType;
use Database\Factories\CandidateApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A single Candidate's application to a single JobRequirement. Rich
 * pivot: carries its OWN current_stage_id and status so one applicant
 * can be in `screening` while a colleague on the same job already
 * reached `interviewing`. See docs/recruitment/05-phase-2-plan.md §2.3.
 */
class CandidateApplication extends Model
{
    /** @use HasFactory<CandidateApplicationFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'application_number',
        'candidate_id',
        'job_requirement_id',
        'current_stage_id',
        'status',
        'source',
        'applied_at',
        'is_shortlisted',
        'shortlisted_at',
        'shortlisted_by_user_id',
        'rejected_at',
        'rejected_by_user_id',
        'rejection_reason',
        'rejection_stage_code',
        'notes',
        'stage_entered_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'applied',
        'is_shortlisted' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CandidateApplicationStatus::class,
            'applied_at' => 'datetime',
            'is_shortlisted' => 'boolean',
            'shortlisted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'stage_entered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<JobRequirement, $this>
     */
    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    /**
     * @return BelongsTo<RecruitmentPipelineStage, $this>
     */
    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentPipelineStage::class, 'current_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function shortlistedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shortlisted_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    /**
     * @return HasOne<CandidateScreening, $this>
     */
    public function screening(): HasOne
    {
        return $this->hasOne(CandidateScreening::class, 'application_id');
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'application_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'entity_id')
            ->where('entity_type', TaskEntityType::CandidateApplication->value);
    }

    /**
     * @param  Builder<CandidateApplication>  $query
     * @return Builder<CandidateApplication>
     */
    public function scopeShortlisted(Builder $query): Builder
    {
        return $query->where('is_shortlisted', true);
    }

    /**
     * @param  Builder<CandidateApplication>  $query
     * @return Builder<CandidateApplication>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            CandidateApplicationStatus::Rejected->value,
            CandidateApplicationStatus::Withdrawn->value,
            CandidateApplicationStatus::Hired->value,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // stage_entered_at updates on every stage advance; the stage
            // id change is the real signal and it IS logged.
            ->logOnly(array_values(array_diff($this->fillable, ['stage_entered_at'])))
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recruitment.candidate_application');
    }
}
