<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskTag;
use App\Modules\Tasks\Requests\StoreTaskTagRequest;
use App\Modules\Tasks\Requests\UpdateTaskTagRequest;
use App\Modules\Tasks\Resources\TaskTagResource;
use App\Modules\Tasks\Services\TaskTagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Also doubles as the tag autocomplete source — index() returns the full
 * (small, hand-curated) list rather than a paginated one.
 */
class TaskTagController extends Controller
{
    public function __construct(
        private readonly TaskTagService $tags,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return TaskTagResource::collection($this->tags->all());
    }

    public function store(StoreTaskTagRequest $request): JsonResponse
    {
        $tag = $this->tags->create($request->validated());

        return (new TaskTagResource($tag))->response()->setStatusCode(201);
    }

    public function show(TaskTag $task_tag): TaskTagResource
    {
        return new TaskTagResource($task_tag);
    }

    public function update(UpdateTaskTagRequest $request, TaskTag $task_tag): TaskTagResource
    {
        return new TaskTagResource($this->tags->update($task_tag, $request->validated()));
    }

    public function destroy(TaskTag $task_tag): JsonResponse
    {
        $this->tags->delete($task_tag);

        return response()->json(null, 204);
    }
}
