<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\CandidateApplication;
use App\Models\Interview;
use App\Models\User;
use App\Modules\Recruitment\Events\InterviewCompleted;
use App\Modules\Recruitment\Events\InterviewScheduled;
use App\Modules\Recruitment\Repositories\CandidateApplicationRepository;
use App\Modules\Recruitment\Repositories\InterviewFeedbackRepository;
use App\Modules\Recruitment\Repositories\InterviewRepository;
use App\Shared\Enums\CandidateApplicationStatus;
use App\Shared\Enums\InterviewKind;
use App\Shared\Enums\InterviewStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Scheduling + lifecycle for Interview. Everything that touches the
 * row is wrapped in DB::transaction so the status + application mirror
 * + number stamp cannot drift apart on a mid-write crash. Terminal
 * guards short-circuit at the top of each mutator so callers never see
 * raw SQL constraint errors.
 */
class InterviewService
{
    public function __construct(
        private readonly InterviewRepository $interviews,
        private readonly InterviewFeedbackRepository $feedbacks,
        private readonly CandidateApplicationRepository $applications,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(CandidateApplication $application, array $data, User $actor): Interview
    {
        return DB::transaction(function () use ($application, $data, $actor): Interview {
            $locked = $this->applications->findForUpdate($application->id);

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'application_id' => 'لا يمكن جدولة مقابلة لطلب مغلق.',
                ]);
            }

            $kind = (string) ($data['kind'] ?? InterviewKind::Internal->value);

            $interview = $this->interviews->create([
                'interview_number' => $this->numbers->nextInterviewNumber(),
                'application_id' => $locked->id,
                'kind' => $kind,
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => (int) ($data['duration_minutes'] ?? 60),
                'timezone' => (string) ($data['timezone'] ?? 'Asia/Gaza'),
                'location' => $data['location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'meeting_notes' => $data['meeting_notes'] ?? null,
                'status' => InterviewStatus::Scheduled->value,
                'created_by_user_id' => $actor->id,
            ]);

            // Mirror the status only on the FIRST schedule for this
            // application — subsequent interviews don't need to touch an
            // already-interviewing row and must never roll back a status
            // the admin advanced past interviewing (e.g. client_review).
            if ($locked->status !== CandidateApplicationStatus::Interviewing) {
                $openInterviewsBefore = $locked->interviews()
                    ->where('id', '!=', $interview->id)
                    ->count();
                if ($openInterviewsBefore === 0) {
                    $this->mirrorApplicationStatus($locked, CandidateApplicationStatus::Interviewing);
                }
            }

            InterviewScheduled::dispatch($interview, $actor);

            return $interview;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reschedule(Interview $interview, array $data, User $actor): Interview
    {
        return DB::transaction(function () use ($interview, $data, $actor): Interview {
            $locked = $this->interviews->findForUpdate($interview->id);

            if ($locked->status !== InterviewStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'status' => 'يمكن إعادة جدولة المقابلات المجدولة فقط.',
                ]);
            }

            // Flip the old row to `rescheduled` so history is preserved —
            // the new row carries rescheduled_from_id back to it.
            $this->interviews->update($locked, [
                'status' => InterviewStatus::Rescheduled->value,
            ]);

            $replacement = $this->interviews->create([
                'interview_number' => $this->numbers->nextInterviewNumber(),
                'application_id' => $locked->application_id,
                'kind' => $locked->kind->value,
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => (int) ($data['duration_minutes'] ?? $locked->duration_minutes),
                'timezone' => (string) ($data['timezone'] ?? $locked->timezone),
                'location' => $data['location'] ?? $locked->location,
                'meeting_url' => $data['meeting_url'] ?? $locked->meeting_url,
                'meeting_notes' => $data['meeting_notes'] ?? $locked->meeting_notes,
                'status' => InterviewStatus::Scheduled->value,
                'rescheduled_from_id' => $locked->id,
                'created_by_user_id' => $actor->id,
            ]);

            InterviewScheduled::dispatch($replacement, $actor);

            return $replacement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Interview $interview, array $data): Interview
    {
        return DB::transaction(function () use ($interview, $data): Interview {
            $locked = $this->interviews->findForUpdate($interview->id);

            if ($locked->status !== InterviewStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'status' => 'يمكن تعديل المقابلات المجدولة فقط.',
                ]);
            }

            // Whitelist the editable columns — status/kind/number are
            // owned by the lifecycle methods, not a PATCH.
            $editable = array_intersect_key($data, array_flip([
                'scheduled_at',
                'duration_minutes',
                'timezone',
                'location',
                'meeting_url',
                'meeting_notes',
            ]));

            return $this->interviews->update($locked, $editable);
        });
    }

    public function cancel(Interview $interview, ?string $reason = null): Interview
    {
        return DB::transaction(function () use ($interview, $reason): Interview {
            $locked = $this->interviews->findForUpdate($interview->id);

            // Terminal statuses are a no-op — a double-click or stale UI
            // must not corrupt the stored reason.
            if (in_array($locked->status, [
                InterviewStatus::Cancelled,
                InterviewStatus::Completed,
                InterviewStatus::Rescheduled,
            ], true)) {
                return $this->interviews->findOrFail($locked->id);
            }

            return $this->interviews->update($locked, [
                'status' => InterviewStatus::Cancelled->value,
                'cancelled_reason' => $reason,
            ]);
        });
    }

    public function complete(Interview $interview): Interview
    {
        return DB::transaction(function () use ($interview): Interview {
            $locked = $this->interviews->findForUpdate($interview->id);

            if ($locked->status !== InterviewStatus::Scheduled) {
                throw ValidationException::withMessages([
                    'status' => 'يمكن إنهاء المقابلات المجدولة فقط.',
                ]);
            }

            $completed = $this->interviews->update($locked, [
                'status' => InterviewStatus::Completed->value,
            ]);

            // Phase 2 Week 3 scope: an interview with at least one
            // feedback counts as "complete enough" to advance the
            // application. Phase 3 will introduce the explicit panelist
            // assignment table that makes "all expected feedback in" a
            // computable condition.
            if ($this->feedbacks->averageScore($completed) !== null) {
                InterviewCompleted::dispatch($completed);
            }

            return $completed;
        });
    }

    private function mirrorApplicationStatus(CandidateApplication $application, CandidateApplicationStatus $status): void
    {
        $this->applications->update($application, [
            'status' => $status->value,
        ]);
    }
}
