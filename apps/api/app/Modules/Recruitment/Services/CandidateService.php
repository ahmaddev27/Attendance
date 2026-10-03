<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\User;
use App\Modules\Recruitment\Repositories\CandidateRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns the Candidate lifecycle: list / find / create / update / soft-
 * delete, plus the dedup lookups the CSV import leans on (findByEmail,
 * findByPhone — both exposed by the repository so this service stays
 * about intent, not SQL).
 */
class CandidateService
{
    public function __construct(
        private readonly CandidateRepository $candidates,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->candidates->paginate($filters, $perPage);
    }

    public function find(int $id): Candidate
    {
        return $this->candidates->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Candidate
    {
        return DB::transaction(function () use ($data, $actor): Candidate {
            // Phone + email are both nullable at the column level, but
            // the Request guarantees at least one is present (same rule
            // the CSV import applies). We normalise an all-whitespace
            // phone to null so the DB index is useful for dedup.
            $data = $this->normalise($data);

            $data['candidate_number'] = $this->numbers->nextCandidateNumber();
            $data['created_by_user_id'] = $actor->id;

            return $this->candidates->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Candidate $candidate, array $data): Candidate
    {
        $data = $this->normalise($data);

        return DB::transaction(fn () => $this->candidates->update($candidate, $data));
    }

    public function softDelete(Candidate $candidate): void
    {
        DB::transaction(fn () => $candidate->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        if (isset($data['email'])) {
            $data['email'] = $data['email'] === '' ? null : mb_strtolower(trim((string) $data['email']));
        }

        if (isset($data['phone'])) {
            $phone = trim((string) $data['phone']);
            $data['phone'] = $phone === '' ? null : $phone;
        }

        return $data;
    }
}
