<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\InterviewRecommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One interviewer's assessment of one Interview. UNIQUE(interview_id,
 * interviewer_user_id) means a panelist's follow-up submission edits
 * their own row — never creates a second one.
 */
class InterviewFeedback extends Model
{
    use LogsActivity;

    // Laravel would pluralize to `interview_feedback` (uncountable word).
    protected $table = 'interview_feedbacks';

    protected $fillable = [
        'interview_id',
        'interviewer_user_id',
        'scorecard',
        'overall_score',
        'recommendation',
        'strengths',
        'weaknesses',
        'notes',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scorecard' => 'array',
            'overall_score' => 'decimal:2',
            'recommendation' => InterviewRecommendation::class,
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recruitment.interview_feedback');
    }
}
