<?php

declare(strict_types=1);

namespace App\Modules\Organization\Repositories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

class TeamRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Team>
     */
    public function list(array $filters): Collection
    {
        $query = Team::query()->with(['department', 'leader']);

        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        return $query->orderBy('name')->get();
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
