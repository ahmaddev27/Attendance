<?php

declare(strict_types=1);

namespace App\Modules\Organization\Repositories;

use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PositionRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Position>
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
     * @return \Illuminate\Database\Eloquent\Builder<Position>
     */
    private function query(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Position::query()
            ->with('department')
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
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('title');
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
