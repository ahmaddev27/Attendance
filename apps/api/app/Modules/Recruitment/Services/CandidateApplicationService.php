<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\JobRequirement;
use App\Models\User;
use App\Modules\Recruitment\Events\CandidateApplicationRejected;
use App\Modules\Recruitment\Events\CandidateApplied;
use App\Modules\Recruitment\Repositories\CandidateApplicationRepository;
use App\Modules\Recruitment\Repositories\RecruitmentPipelineRepository;
use App\Shared\Enums\CandidateApplicationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the Candidate ↔ Job pivot lifecycle. Phase 2 Week 1 scope:
 * attach/list/update/reject/withdraw. The stage advance for an
 * application is Week 3 work (uses the same Pipeline Engine Phase 1
 * built for JobRequirement).
 */
class CandidateApplicationService
{
    public function __construct(
        private readonly CandidateApplicationRepository $applications,
        private readonly RecruitmentPipelineRepository $pipelines,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForJob(JobRequirement $job, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->applications->paginateForJob($job, $filters, $perPage);
    }

    public function paginateForCandidate(Candidate $candidate, int $perPage = 25): LengthAwarePaginator
    {
        return $this->applications->paginateForCandidate($candidate->id, $perPage);
    }

    public function find(int $id): CandidateApplication
    {
        return $this->applications->findOrFail($id);
    }

    /**
     * Attach an existing candidate to a job. The UNIQUE index on
     * (candidate_id, job_requirement_id) is the hard backstop; this
     * check surfaces the duplicate as a validation error instead of a
     * raw SQL exception.
     *
     * @param  array<string, mixed>  $data   accepts: source, notes
     */
    public function attach(JobRequirement $job, Candidate $candidate, User $actor, array $data = []): CandidateApplication
    {
        return DB::transaction(function () use ($job, $candidate, $actor, $data): CandidateApplication {
            if ($this->applications->existsForJob($candidate->id, $job->id)) {
                throw ValidationException::withMessages([
                    'candidate_id' => 'هذا المرشّح متقدّم على هذه الوظيفة بالفعل.',
                ]);
            }

            // Start at the job's current stage — a new application lands
            // where the job is TODAY and advances independently from
            // there. See CandidateApplication::$current_stage_id comment.
            $stageId = $job->current_stage_id ?? $this->firstStageId($job);

            $application = $this->applications->create([
                'application_number' => $this->numbers->nextApplicationNumber(),
                'candidate_id' => $candidate->id,
                'job_requirement_id' => $job->id,
                'current_stage_id' => $stageId,
                'status' => CandidateApplicationStatus::Applied->value,
                'source' => (string) ($data['source'] ?? 'manual'),
                'applied_at' => now(),
                'notes' => $data['notes'] ?? null,
                'stage_entered_at' => now(),
            ]);

            // Dispatch inside the transaction is intentional — the
            // listeners (screening task, notifications) read the row
            // back and would miss it on a pre-commit dispatch.
            CandidateApplied::dispatch($application, $actor);

            return $application;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CandidateApplication $application, array $data): CandidateApplication
    {
        return DB::transaction(fn () => $this->applications->update($application, $data));
    }

    public function reject(CandidateApplication $application, User $actor, string $reason): CandidateApplication
    {
        return DB::transaction(function () use ($application, $actor, $reason): CandidateApplication {
            $locked = $this->applications->findForUpdate($application->id);

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => 'هذا الطلب مغلق بالفعل ولا يمكن رفضه مجدداً.',
                ]);
            }

            $stageCode = $locked->currentStage()->first()?->code;

            $updated = $this->applications->update($locked, [
                'status' => CandidateApplicationStatus::Rejected->value,
                'rejected_at' => now(),
                'rejected_by_user_id' => $actor->id,
                'rejection_reason' => $reason,
                'rejection_stage_code' => $stageCode,
            ]);

            CandidateApplicationRejected::dispatch($updated, $actor, $reason);

            return $updated;
        });
    }

    public function withdraw(CandidateApplication $application): CandidateApplication
    {
        return DB::transaction(function () use ($application): CandidateApplication {
            $locked = $this->applications->findForUpdate($application->id);

            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => 'هذا الطلب مغلق بالفعل ولا يمكن سحبه مجدداً.',
                ]);
            }

            return $this->applications->update($locked, [
                'status' => CandidateApplicationStatus::Withdrawn->value,
            ]);
        });
    }

    private function firstStageId(JobRequirement $job): int
    {
        $pipeline = $this->pipelines->findOrFail($job->pipeline_id);
        $first = $pipeline->stages()->orderBy('display_order')->first();

        if ($first === null) {
            throw ValidationException::withMessages([
                'current_stage_id' => 'لا توجد مراحل مُعرّفة في مسار هذه الوظيفة.',
            ]);
        }

        return $first->id;
    }
}
