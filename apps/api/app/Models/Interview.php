<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\InterviewKind;
use App\Shared\Enums\InterviewStatus;
use App\Shared\Enums\TaskEntityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A scheduled interview for one CandidateApplication. `kind` picks
 * between internal (TAQAT panel) and client interviews — same schema,
 * different visibility (D4). Panel feedback is one row per interviewer
 * on `interview_feedbacks`, never a shared JSON blob here.
 */
class Interview extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'interview_number',
        'application_id',
        'kind',
        'scheduled_at',
        'duration_minutes',
        'timezone',
        'location',
        'meeting_url',
        'meeting_notes',
        'status',
        'cancelled_reason',
        'rescheduled_from_id',
        'created_by_user_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'scheduled',
        'duration_minutes' => 60,
        'timezone' => 'Asia/Gaza',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InterviewKind::class,
            'status' => InterviewStatus::class,
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
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
     * @return BelongsTo<Interview, $this>
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(Interview::class, 'rescheduled_from_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<InterviewFeedback, $this>
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'entity_id')
            ->where('entity_type', TaskEntityType::Interview->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recruitment.interview');
    }
}
