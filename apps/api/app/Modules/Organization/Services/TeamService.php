<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Models\Team;
use App\Modules\Organization\Repositories\TeamRepository;
use Illuminate\Database\Eloquent\Collection;

class TeamService
{
    public function __construct(
        private readonly TeamRepository $teams,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Team>
     */
    public function list(array $filters): Collection
    {
        return $this->teams->list($filters);
    }

    public function find(int $id): Team
    {
        return $this->teams->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Team
    {
        return $this->teams->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Team $team, array $data): Team
    {
        return $this->teams->update($team, $data);
    }

    public function delete(Team $team): bool
    {
        return $this->teams->delete($team);
    }
}
