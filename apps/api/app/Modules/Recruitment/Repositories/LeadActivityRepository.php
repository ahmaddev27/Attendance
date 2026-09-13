<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeadActivityRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['user'];

    public function paginateForLead(Lead $lead, int $perPage): LengthAwarePaginator
    {
        return LeadActivity::query()
            ->with(self::WITH)
            ->where('lead_id', $lead->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LeadActivity
    {
        return LeadActivity::query()->create($data)->load(self::WITH);
    }
}
