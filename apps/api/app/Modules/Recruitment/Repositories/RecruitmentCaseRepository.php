<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\Client;
use App\Models\RecruitmentCase;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RecruitmentCaseRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['client', 'owner', 'sourceLead'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = RecruitmentCase::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function paginateForClient(Client $client, int $perPage): LengthAwarePaginator
    {
        return RecruitmentCase::query()
            ->with(self::WITH)
            ->where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): RecruitmentCase
    {
        return RecruitmentCase::query()->with(self::WITH)->findOrFail($id);
    }

    public function findForUpdate(int $id): RecruitmentCase
    {
        return RecruitmentCase::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RecruitmentCase
    {
        return RecruitmentCase::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RecruitmentCase $case, array $data): RecruitmentCase
    {
        $case->fill($data)->save();

        return $case->fresh(self::WITH) ?? $case;
    }

    public function highestSequenceForYear(int $year): int
    {
        $prefix = "RC-{$year}-";

        $latest = RecruitmentCase::query()
            ->where('case_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('case_number');

        if ($latest === null) {
            return 0;
        }

        return (int) mb_substr((string) $latest, mb_strlen($prefix));
    }

    /**
     * @param  Builder<RecruitmentCase>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['client_id', 'owner_id', 'status', 'priority'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('open_only', $filters) && $filters['open_only']) {
            $query->whereIn('status', [
                RecruitmentCaseStatus::Draft->value,
                RecruitmentCaseStatus::Active->value,
                RecruitmentCaseStatus::OnHold->value,
            ]);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('case_number', 'like', "%{$search}%");
            });
        }
    }
}
