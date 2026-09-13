<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\Lead;
use App\Shared\Enums\LeadStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeadRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['owner', 'convertedClient'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Lead::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Every non-terminal lead grouped by status — the shape the Kanban
     * board consumes directly. Ordered inside each column by
     * next_followup_at NULLS LAST so overdue follow-ups float up.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<string, Collection<int, Lead>>
     */
    public function groupByStatus(array $filters = []): Collection
    {
        $query = Lead::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByRaw('CASE WHEN next_followup_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_followup_at')
            ->get()
            ->groupBy(fn (Lead $lead) => $lead->status->value);
    }

    public function findOrFail(int $id): Lead
    {
        return Lead::query()->with(self::WITH)->findOrFail($id);
    }

    /**
     * Row-locked reload for use inside a transaction that will either
     * flip the lead to Converted or mark it Lost — prevents two staff
     * from racing on the same lead.
     */
    public function findForUpdate(int $id): Lead
    {
        return Lead::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Lead
    {
        return Lead::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lead $lead, array $data): Lead
    {
        $lead->fill($data)->save();

        return $lead->fresh(self::WITH) ?? $lead;
    }

    /**
     * Case-insensitive dedup check on (company_name, country). Excludes
     * the row itself so the same tuple is allowed on update.
     */
    public function duplicateExists(string $companyName, ?string $country, ?int $ignoreId = null): bool
    {
        $query = Lead::query()
            ->whereRaw('LOWER(company_name) = ?', [mb_strtolower($companyName)])
            ->where(function (Builder $q) use ($country) {
                if ($country === null || $country === '') {
                    $q->whereNull('country');
                } else {
                    $q->whereRaw('LOWER(country) = ?', [mb_strtolower($country)]);
                }
            });

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * The largest lead_number issued in a given year, used by the
     * LeadNumberGenerator to compute the next sequential id atomically
     * inside a transaction.
     */
    public function highestSequenceForYear(int $year): int
    {
        $prefix = "L-{$year}-";

        $latest = Lead::query()
            ->where('lead_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('lead_number');

        if ($latest === null) {
            return 0;
        }

        return (int) mb_substr((string) $latest, mb_strlen($prefix));
    }

    /**
     * Active leads whose last touch is older than the cutoff — the
     * "stale lead" scheduler feeds this to NotificationService to
     * remind owners to follow up.
     *
     * @return Collection<int, Lead>
     */
    public function staleActive(Carbon $cutoff): Collection
    {
        return Lead::query()
            ->with('owner')
            ->active()
            ->where(function (Builder $q) use ($cutoff) {
                $q->whereNull('last_contact_at')
                    ->orWhere('last_contact_at', '<', $cutoff);
            })
            ->get();
    }

    /**
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['owner_id', 'status', 'source', 'country', 'industry'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['statuses']) && is_array($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        if (array_key_exists('active_only', $filters) && $filters['active_only']) {
            $query->whereNotIn('status', [
                LeadStatus::Converted->value,
                LeadStatus::Lost->value,
            ]);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('lead_number', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // Index-friendly range filter — never wraps the column in
        // DATE(...) so any index on next_followup_at is usable.
        if (! empty($filters['followup_from'])) {
            $query->where('next_followup_at', '>=', $filters['followup_from']);
        }

        if (! empty($filters['followup_to'])) {
            $query->where('next_followup_at', '<', Carbon::parse($filters['followup_to'])->addDay());
        }
    }
}
