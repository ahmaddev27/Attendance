<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ClientRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['accountManager', 'sourceLead', 'primaryContact'];

    /**
     * Extended relation set for the /clients/{id}/profile endpoint — the
     * front page wants active cases counted and the primary contact
     * visible without a second round-trip.
     *
     * @var list<string>
     */
    private const WITH_PROFILE = [
        'accountManager',
        'sourceLead',
        'contacts',
        'cases',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Client::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function findOrFail(int $id): Client
    {
        return Client::query()->with(self::WITH)->findOrFail($id);
    }

    public function findForProfile(int $id): Client
    {
        return Client::query()->with(self::WITH_PROFILE)->findOrFail($id);
    }

    public function findForUpdate(int $id): Client
    {
        return Client::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Client
    {
        return Client::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client
    {
        $client->fill($data)->save();

        return $client->fresh(self::WITH) ?? $client;
    }

    /**
     * Case-insensitive dedup check on (company_name, country) —
     * mirrors the DB-level UNIQUE (company_name, country) but runs
     * before the insert so the UX can offer a "reuse existing" path
     * instead of a 500.
     */
    public function duplicateExists(string $companyName, ?string $country, ?int $ignoreId = null): bool
    {
        $query = Client::query()
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
     * See LeadRepository::highestSequenceForYear — same generator pattern
     * per numbering namespace ("C-YYYY-####").
     */
    public function highestSequenceForYear(int $year): int
    {
        $prefix = "C-{$year}-";

        $latest = Client::query()
            ->where('client_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('client_number');

        if ($latest === null) {
            return 0;
        }

        return (int) mb_substr((string) $latest, mb_strlen($prefix));
    }

    /**
     * @param  Builder<Client>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['status', 'account_manager_id', 'country', 'industry'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('client_number', 'like', "%{$search}%");
            });
        }
    }
}
