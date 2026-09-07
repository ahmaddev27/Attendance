<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Models\Position;
use App\Modules\Organization\Repositories\PositionRepository;
use Illuminate\Database\Eloquent\Collection;

class PositionService
{
    public function __construct(
        private readonly PositionRepository $positions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Position>
     */
    public function list(array $filters): Collection
    {
        return $this->positions->list($filters);
    }

    public function find(int $id): Position
    {
        return $this->positions->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Position
    {
        return $this->positions->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Position $position, array $data): Position
    {
        return $this->positions->update($position, $data);
    }

    public function delete(Position $position): bool
    {
        return $this->positions->delete($position);
    }
}
