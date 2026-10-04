<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\Interview;
use App\Models\InterviewFeedback;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads + writes for InterviewFeedback. The (interview_id,
 * interviewer_user_id) UNIQUE index means every submit is really an
 * upsert — the service relies on that to turn a second submission into
 * an edit rather than a duplicate row.
 */
class InterviewFeedbackRepository
{
    /**
     * @return Collection<int, InterviewFeedback>
     */
    public function forInterview(Interview $interview): Collection
    {
        return InterviewFeedback::query()
            ->with('interviewer')
            ->where('interview_id', $interview->id)
            ->orderBy('submitted_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(Interview $interview, int $interviewerUserId, array $data): InterviewFeedback
    {
        /** @var InterviewFeedback $feedback */
        $feedback = InterviewFeedback::query()->updateOrCreate(
            [
                'interview_id' => $interview->id,
                'interviewer_user_id' => $interviewerUserId,
            ],
            $data,
        );

        return $feedback->fresh('interviewer') ?? $feedback;
    }

    /**
     * SQL AVG keeps the computation on the DB so a committee of 5 doesn't
     * cost 5 round-trips. Returns null when the interview has no
     * feedbacks yet (SQL AVG of nothing is NULL, not 0).
     */
    public function averageScore(Interview $interview): ?float
    {
        $avg = InterviewFeedback::query()
            ->where('interview_id', $interview->id)
            ->whereNotNull('overall_score')
            ->avg('overall_score');

        return $avg === null ? null : round((float) $avg, 2);
    }

    public function findForInterview(Interview $interview, int $feedbackId): ?InterviewFeedback
    {
        return InterviewFeedback::query()
            ->where('interview_id', $interview->id)
            ->where('id', $feedbackId)
            ->first();
    }
}
