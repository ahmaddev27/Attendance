<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\CandidateStatus;
use App\Shared\Enums\TaskEntityType;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A single person inside the Recruitment Candidate bank. Shared across
 * every JobRequirement — a candidate who was screened out of Role A in
 * Q1 may be the perfect fit for Role B in Q3, so we never duplicate the
 * person. Per-job state (stage, scorecard, shortlist flag) lives on
 * CandidateApplication, not here.
 */
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'candidate_number',
        'full_name',
        'email',
        'phone',
        'country',
        'city',
        'linkedin_url',
        'portfolio_url',
        'resume_path',
        'resume_uploaded_at',
        'status',
        'source',
        'source_reference',
        'headline',
        'years_of_experience',
        'current_title',
        'current_company',
        'expected_salary_min',
        'expected_salary_max',
        'salary_currency',
        'availability',
        'skills',
        'languages',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'salary_currency' => 'USD',
    ];

    /**
     * The private-disk resume path is server-only — the Resource emits
     * a short-lived signed URL for the client instead.
     *
     * @var list<string>
     */
    protected $hidden = [
        'resume_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CandidateStatus::class,
            'years_of_experience' => 'integer',
            'expected_salary_min' => 'decimal:2',
            'expected_salary_max' => 'decimal:2',
            'resume_uploaded_at' => 'datetime',
            'skills' => 'array',
            'languages' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<CandidateApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class);
    }

    /**
     * Auto-generated tasks that hang off this candidate (e.g. follow-up
     * reminders). Query scoped by entity_type so a task pointing at a
     * different entity with the same integer id does not leak in.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'entity_id')
            ->where('entity_type', TaskEntityType::Candidate->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Resume uploads are their own event via events — logging the
        // path here would doxx the UUID into the audit stream. Everything
        // else is the business signal admins actually audit.
        return LogOptions::defaults()
            ->logOnly(array_values(array_diff($this->fillable, ['resume_path', 'resume_uploaded_at'])))
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recruitment.candidate');
    }
}
