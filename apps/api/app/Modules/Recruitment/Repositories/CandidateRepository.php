<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\Candidate;
use App\Shared\Enums\CandidateStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads + writes for the shared candidate bank. Dedup lookups (email,
 * phone) live here so CandidateImportService stays about CSV parsing,
 * not SQL — same split as Phase 1 (LeadRepository::findByEmail etc.).
 */
class CandidateRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['createdByUser'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Candidate::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function findOrFail(int $id): Candidate
    {
        return Candidate::query()->with(self::WITH)->findOrFail($id);
    }

    public function findForUpdate(int $id): Candidate
    {
        return Candidate::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * Soft-dedup helpers for CSV import. Email is matched
     * case-insensitively; phone is matched as-stored (the service
     * normalises before calling). Null-safe — a candidate without
     * either column is never a dedup target.
     */
    public function findByEmail(?string $email): ?Candidate
    {
        if ($email === null || $email === '') {
            return null;
        }

        return Candidate::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();
    }

    public function findByPhone(?string $phone): ?Candidate
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        return Candidate::query()->where('phone', $phone)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Candidate
    {
        return Candidate::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Candidate $candidate, array $data): Candidate
    {
        $candidate->fill($data)->save();

        return $candidate->fresh(self::WITH) ?? $candidate;
    }

    public function highestSequenceForYear(int $year): int
    {
        $prefix = "CAN-{$year}-";

        // withTrashed: candidate_number has a UNIQUE index that still
        // covers soft-deleted rows, same pattern as the rest of the
        // Recruitment numbers (fixed in commit `0c1c303`).
        $latest = Candidate::withTrashed()
            ->where('candidate_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('candidate_number');

        if ($latest === null) {
            return 0;
        }

        return (int) mb_substr((string) $latest, mb_strlen($prefix));
    }

    /**
     * @param  Builder<Candidate>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $term = '%'.mb_strtolower((string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->whereRaw('LOWER(full_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('candidate_number', 'like', $term);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (! empty($filters['has_resume'])) {
            $value = filter_var($filters['has_resume'], FILTER_VALIDATE_BOOLEAN);
            $value ? $query->whereNotNull('resume_path') : $query->whereNull('resume_path');
        }

        if (! empty($filters['min_experience'])) {
            $query->where('years_of_experience', '>=', (int) $filters['min_experience']);
        }

        // Default: hide blacklisted + inactive unless caller explicitly
        // asks for them. Keeps the main picker clean without a dedicated
        // "active only" scope on every call site.
        if (! array_key_exists('status', $filters) && empty($filters['include_closed'])) {
            $query->whereNotIn('status', [
                CandidateStatus::Blacklisted->value,
                CandidateStatus::Inactive->value,
            ]);
        }
    }
}
