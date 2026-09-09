<?php

declare(strict_types=1);

namespace App\Modules\Organization\Repositories;

use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TeamRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Team>
     */
    public function list(array $filters): Collection
    {
        return $this->query($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder<Team>
     */
    private function query(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Team::query()
            ->with(['department', 'leader'])
            ->withCount('employees');

        // Legacy `active` alias — kept for callers that still pass the
        // old key. The web + tests use `is_active` now.
        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name');
    }

    public function findOrFail(int $id): Team
    {
        return Team::query()->with(['department', 'leader'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Team
    {
        return Team::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Team $team, array $attributes): Team
    {
        $team->update($attributes);

        return $team->refresh();
    }

    public function delete(Team $team): bool
    {
        return (bool) $team->delete();
    }
}
