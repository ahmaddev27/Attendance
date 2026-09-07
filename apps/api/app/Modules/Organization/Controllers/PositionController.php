<?php

declare(strict_types=1);

namespace App\Modules\Organization\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Modules\Organization\Requests\StorePositionRequest;
use App\Modules\Organization\Requests\UpdatePositionRequest;
use App\Modules\Organization\Resources\PositionResource;
use App\Modules\Organization\Services\PositionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PositionController extends Controller
{
    public function __construct(
        private readonly PositionService $positions,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $positions = $this->positions->list($request->only(['active', 'department_id']));

        return PositionResource::collection($positions);
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = $this->positions->create($request->validated());

        return (new PositionResource($position->load('department')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Position $position): PositionResource
    {
        return new PositionResource($position->load('department'));
    }

    public function update(UpdatePositionRequest $request, Position $position): PositionResource
    {
        $position = $this->positions->update($position, $request->validated());

        return new PositionResource($position->load('department'));
    }

    public function destroy(Position $position): JsonResponse
    {
        $this->positions->delete($position);

        return response()->json(['message' => 'Position deleted.']);
    }
}
