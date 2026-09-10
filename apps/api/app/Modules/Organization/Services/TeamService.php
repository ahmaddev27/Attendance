<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Models\Team;
use App\Modules\Organization\Repositories\TeamRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

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

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->teams->paginate($filters, $perPage);
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
        // Guard against silent orphaning: if the team still has employees
        // assigned, either the delete cascades and their `team_id` goes
        // NULL without an audit trail, or the FK constraint returns a
        // raw 1451 error to the client. Neither is acceptable — force
        // the operator to move the members first.
        if ($team->employees()->count() > 0) {
            throw ValidationException::withMessages([
                'id' => 'لا يمكن حذف الفريق بينما يحتوي على موظفين. يرجى نقلهم أولاً.',
            ]);
        }

        return $this->teams->delete($team);
    }
}
