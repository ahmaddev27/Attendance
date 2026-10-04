<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\User;
use App\Modules\Recruitment\Events\FeedbackSubmitted;
use App\Modules\Recruitment\Repositories\InterviewFeedbackRepository;
use App\Shared\Enums\InterviewStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the per-interviewer feedback row. The UNIQUE index on
 * (interview_id, interviewer_user_id) means a second submission by the
 * same panelist is really an edit — the service upserts, never inserts
 * a duplicate.
 */
class InterviewFeedbackService
{
    public function __construct(
        private readonly InterviewFeedbackRepository $feedbacks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(Interview $interview, User $interviewer, array $data): InterviewFeedback
    {
        return DB::transaction(function () use ($interview, $interviewer, $data): InterviewFeedback {
            if (! $interviewer->can('submit-interview-feedback')) {
                throw ValidationException::withMessages([
                    'permission' => 'لا تملك صلاحية تقديم ملاحظات على المقابلات.',
                ]);
            }

            if ($interview->status === InterviewStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تقديم ملاحظات على مقابلة ملغاة.',
                ]);
            }

            // Phase 2 Week 3 scope: accept any user with the permission
            // for simplicity — the real "must be on the panel" check
            // needs the explicit panelist assignment table that lands in
            // Phase 3. Until then the permission alone is the gate.
            // See docs/recruitment/05-phase-2-plan.md §10.3.

            $overall = $this->deriveOverallScore((array) ($data['scorecard'] ?? []));

            $feedback = $this->feedbacks->upsert($interview, $interviewer->id, [
                'scorecard' => (array) ($data['scorecard'] ?? []),
                'overall_score' => $overall,
                'recommendation' => (string) ($data['recommendation'] ?? ''),
                'strengths' => $data['strengths'] ?? null,
                'weaknesses' => $data['weaknesses'] ?? null,
                'notes' => $data['notes'] ?? null,
                'submitted_at' => now(),
            ]);

            FeedbackSubmitted::dispatch($feedback, $interviewer);

            return $feedback;
        });
    }

    public function averageScore(Interview $interview): ?float
    {
        return $this->feedbacks->averageScore($interview);
    }

    /**
     * Mean of the numeric values on the scorecard. Scorecards whose
     * fields are all non-numeric (e.g. a free-form "notes" scorecard)
     * yield null — the DB-level AVG then also yields null, which is
     * exactly the signal callers use to detect "no scores yet".
     *
     * @param  array<string, mixed>  $scorecard
     */
    private function deriveOverallScore(array $scorecard): ?float
    {
        $numeric = array_filter($scorecard, static fn ($v) => is_numeric($v));

        if ($numeric === []) {
            return null;
        }

        $sum = array_sum(array_map(static fn ($v) => (float) $v, $numeric));

        return round($sum / count($numeric), 2);
    }
}
