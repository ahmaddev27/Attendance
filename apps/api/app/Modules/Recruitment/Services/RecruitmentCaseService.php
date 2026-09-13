<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Client;
use App\Models\RecruitmentCase;
use App\Modules\Recruitment\Repositories\RecruitmentCaseRepository;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RecruitmentCaseService
{
    public function __construct(
        private readonly RecruitmentCaseRepository $cases,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->cases->paginate($filters, $perPage);
    }

    public function paginateForClient(Client $client, int $perPage = 25): LengthAwarePaginator
    {
        return $this->cases->paginateForClient($client, $perPage);
    }

    public function find(int $id): RecruitmentCase
    {
        return $this->cases->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RecruitmentCase
    {
        return DB::transaction(function () use ($data) {
            $data['case_number'] = $this->numbers->nextCaseNumber();

            return $this->cases->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RecruitmentCase $case, array $data): RecruitmentCase
    {
        // Auto-stamp completed_at on the transition into Completed /
        // Cancelled so KPI reports don't have to synthesise it from
        // updated_at (which lies after any later edit).
        if (isset($data['status'])) {
            $incoming = RecruitmentCaseStatus::from((string) $data['status']);

            if (
                in_array($incoming, [RecruitmentCaseStatus::Completed, RecruitmentCaseStatus::Cancelled], true)
                && $case->completed_at === null
            ) {
                $data['completed_at'] = $data['completed_at'] ?? now();
            }
        }

        return $this->cases->update($case, $data);
    }

    public function delete(RecruitmentCase $case): void
    {
        $case->delete();
    }
}
