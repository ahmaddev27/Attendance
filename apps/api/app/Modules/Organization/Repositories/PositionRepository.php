<?php

declare(strict_types=1);

namespace App\Modules\Organization\Repositories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Collection;

class PositionRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Position>
     */
    public function list(array $filters): Collection
    {
        $query = Position::query()->with('department');

        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $query->where('is_active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        return $query->orderBy('title')->get();
    }

    public function findOrFail(int $id): Position
    {
        return Position::query()->with('department')->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Position
    {
        return Position::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Position $position, array $attributes): Position
    {
        $position->update($attributes);

        return $position->refresh();
    }

    public function delete(Position $position): bool
    {
        return (bool) $position->delete();
    }
}
