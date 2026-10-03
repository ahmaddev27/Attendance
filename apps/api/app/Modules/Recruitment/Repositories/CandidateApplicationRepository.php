<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\CandidateApplication;
use App\Models\JobRequirement;
use App\Shared\Enums\CandidateApplicationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads + writes for CandidateApplication (candidate ↔ job pivot). The
 * pivot carries its own stage pointer and status, so most of this
 * repo's filters are at the (job, status) or (job, is_shortlisted)
 * level — the index pair that supports both exists in migration
 * 2026_11_01_100002.
 */
class CandidateApplicationRepository
{
    /**
     * @var list<string>
     */
    private const WITH = [
        'candidate',
        'jobRequirement',
        'currentStage',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForJob(JobRequirement $job, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = CandidateApplication::query()
            ->with(self::WITH)
            ->where('job_requirement_id', $job->id);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('applied_at')->paginate($perPage);
    }

    public function paginateForCandidate(int $candidateId, int $perPage): LengthAwarePaginator
    {
        return CandidateApplication::query()
            ->with(self::WITH)
            ->where('candidate_id', $candidateId)
            ->orderByDesc('applied_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): CandidateApplication
    {
        return CandidateApplication::query()->with(self::WITH)->findOrFail($id);
    }

    public function findForUpdate(int $id): CandidateApplication
    {
        return CandidateApplication::query()->lockForUpdate()->findOrFail($id);
    }

    public function existsForJob(int $candidateId, int $jobId): bool
    {
        return CandidateApplication::query()
            ->where('candidate_id', $candidateId)
            ->where('job_requirement_id', $jobId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CandidateApplication
    {
        return CandidateApplication::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CandidateApplication $application, array $data): CandidateApplication
    {
        $application->fill($data)->save();

        return $application->fresh(self::WITH) ?? $application;
    }

    public function highestSequenceForYear(int $year): int
    {
        $prefix = "APP-{$year}-";

        $latest = CandidateApplication::withTrashed()
            ->where('application_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('application_number');

        if ($latest === null) {
            return 0;
        }

        return (int) mb_substr((string) $latest, mb_strlen($prefix));
    }

    /**
     * @return array{applied: int, in_screening: int, screened_in: int, screened_out: int, shortlisted: int, interviewing: int, client_review: int, offered: int, rejected: int, withdrawn: int, hired: int}
     */
    public function countsByStatusForJob(JobRequirement $job): array
    {
        $counts = CandidateApplication::query()
            ->where('job_requirement_id', $job->id)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $out = [];
        foreach (CandidateApplicationStatus::cases() as $status) {
            $out[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        /** @var array{applied: int, in_screening: int, screened_in: int, screened_out: int, shortlisted: int, interviewing: int, client_review: int, offered: int, rejected: int, withdrawn: int, hired: int} $out */
        return $out;
    }

    /**
     * @param  Builder<CandidateApplication>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('is_shortlisted', $filters)) {
            $query->where('is_shortlisted', filter_var($filters['is_shortlisted'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['stage_id'])) {
            $query->where('current_stage_id', (int) $filters['stage_id']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.mb_strtolower((string) $filters['search']).'%';
            $query->whereHas('candidate', function (Builder $q) use ($term): void {
                $q->whereRaw('LOWER(full_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhere('candidate_number', 'like', $term);
            });
        }

        if (empty($filters['include_closed'])) {
            $query->whereNotIn('status', [
                CandidateApplicationStatus::Rejected->value,
                CandidateApplicationStatus::Withdrawn->value,
            ]);
        }
    }
}
