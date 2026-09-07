<?php

declare(strict_types=1);

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Modules\Organization\Requests\StoreTeamRequest;
use App\Modules\Organization\Requests\UpdateTeamRequest;
use App\Modules\Organization\Resources\TeamResource;
use App\Modules\Organization\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamService $teams,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $teams = $this->teams->list($request->only(['active', 'department_id']));

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = $this->teams->create($request->validated());

        return (new TeamResource($team->load(['department', 'leader'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Team $team): TeamResource
    {
        return new TeamResource($team->load(['department', 'leader']));
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team = $this->teams->update($team, $request->validated());

        return new TeamResource($team->load(['department', 'leader']));
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->teams->delete($team);

        return response()->json(['message' => 'Team deleted.']);
    }
}
