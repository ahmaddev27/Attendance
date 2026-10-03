<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\CandidateApplication;
use App\Models\JobRequirement;
use App\Models\User;
use App\Modules\Recruitment\Repositories\CandidateApplicationRepository;
use App\Shared\Enums\CandidateApplicationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shortlist is a FLAG on CandidateApplication (D2 in the plan) — no
 * separate table. This service owns the toggle so the two writes
 * (boolean + metadata) stay transactional and auditable, and so the
 * same business rules apply whether the toggle comes from the admin UI
 * or the Pipeline Engine's auto-advance later on.
 */
class CandidateShortlistService
{
    public function __construct(
        private readonly CandidateApplicationRepository $applications,
    ) {}

    public function add(CandidateApplication $application, User $actor): CandidateApplication
    {
        return DB::transaction(function () use ($application, $actor): CandidateApplication {
            $locked = $this->applications->findForUpdate($application->id);

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إدراج طلب مغلق ضمن القائمة القصيرة.',
                ]);
            }

            if ($locked->is_shortlisted) {
                return $locked->fresh(['candidate', 'jobRequirement', 'currentStage']) ?? $locked;
            }

            return $this->applications->update($locked, [
                'is_shortlisted' => true,
                'shortlisted_at' => now(),
                'shortlisted_by_user_id' => $actor->id,
                // Status mirror so admins listing by status alone see the
                // shortlisted cohort without having to join on the flag.
                'status' => CandidateApplicationStatus::Shortlisted->value,
            ]);
        });
    }

    public function remove(CandidateApplication $application): CandidateApplication
    {
        return DB::transaction(function () use ($application): CandidateApplication {
            $locked = $this->applications->findForUpdate($application->id);

            if (! $locked->is_shortlisted) {
                return $locked->fresh(['candidate', 'jobRequirement', 'currentStage']) ?? $locked;
            }

            // Roll the status back to the pre-shortlist default. We don't
            // know what status the admin set earlier (shortlist is one
            // click, not a wizard) so `applied` is the safe neutral — the
            // UI's next stage advance picks the right status.
            $nextStatus = $locked->status === CandidateApplicationStatus::Shortlisted
                ? CandidateApplicationStatus::Applied->value
                : $locked->status->value;

            return $this->applications->update($locked, [
                'is_shortlisted' => false,
                'shortlisted_at' => null,
                'shortlisted_by_user_id' => null,
                'status' => $nextStatus,
            ]);
        });
    }

    public function paginateForJob(JobRequirement $job, int $perPage = 25): LengthAwarePaginator
    {
        return $this->applications->paginateForJob(
            $job,
            ['is_shortlisted' => true],
            $perPage,
        );
    }
}
