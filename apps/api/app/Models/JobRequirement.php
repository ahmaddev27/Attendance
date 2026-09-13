<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\JobRequirementStatus;
use App\Shared\Enums\TaskEntityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A single open position inside a RecruitmentCase — the entity the
 * Pipeline Engine walks stage-by-stage. The (pipeline_id,
 * current_stage_id) pair is the operational state; the coarse `status`
 * column exists in parallel so listing/filtering across many jobs
 * doesn't have to reach into the stage table.
 */
class JobRequirement extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'job_number',
        'recruitment_case_id',
        'pipeline_id',
        'current_stage_id',
        'owner_id',
        'title',
        'department',
        'openings',
        'employment_type',
        'work_mode',
        'location',
        'salary_min',
        'salary_max',
        'salary_currency',
        'required_experience_years',
        'education_level',
        'required_skills',
        'nice_to_have_skills',
        'required_languages',
        'description',
        'responsibilities',
        'publication_url',
        'published_at',
        'application_deadline',
        'target_start_date',
        'status',
        'stage_entered_at',
        'completed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'openings' => 1,
        'salary_currency' => 'USD',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobRequirementStatus::class,
            'openings' => 'integer',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'required_experience_years' => 'integer',
            'required_skills' => 'array',
            'nice_to_have_skills' => 'array',
            'required_languages' => 'array',
            'published_at' => 'datetime',
            'application_deadline' => 'date',
            'target_start_date' => 'date',
            'stage_entered_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RecruitmentCase, $this>
     */
    public function recruitmentCase(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCase::class, 'recruitment_case_id');
    }

    /**
     * @return BelongsTo<RecruitmentPipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(RecruitmentPipeline::class, 'pipeline_id');
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The auto-generated recruitment tasks that hang off this job.
     * Query is scoped by entity_type so a task for a different entity
     * type sharing the same integer id doesn't leak in.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'entity_id')
            ->where('entity_type', TaskEntityType::JobRequirement->value);
    }

    /**
     * @param  Builder<JobRequirement>  $query
     * @return Builder<JobRequirement>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            JobRequirementStatus::Draft->value,
            JobRequirementStatus::Active->value,
            JobRequirementStatus::OnHold->value,
        ]);
    }

    /**
     * See Lead::getActivitylogOptions — same trade-offs, focused on
     * business fields to keep the timeline signal-rich.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recruitment.job_requirement');
    }
}
