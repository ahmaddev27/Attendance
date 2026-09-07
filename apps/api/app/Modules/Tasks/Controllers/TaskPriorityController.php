<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskPriority;
use App\Modules\Tasks\Requests\StoreTaskPriorityRequest;
use App\Modules\Tasks\Requests\UpdateTaskPriorityRequest;
use App\Modules\Tasks\Resources\TaskPriorityResource;
use App\Modules\Tasks\Services\TaskPriorityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskPriorityController extends Controller
{
    public function __construct(
        private readonly TaskPriorityService $priorities,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return TaskPriorityResource::collection($this->priorities->all());
    }

    public function store(StoreTaskPriorityRequest $request): JsonResponse
    {
        $priority = $this->priorities->create($request->validated());

        return (new TaskPriorityResource($priority))->response()->setStatusCode(201);
    }

    public function show(TaskPriority $task_priority): TaskPriorityResource
    {
        return new TaskPriorityResource($task_priority);
    }

    public function update(UpdateTaskPriorityRequest $request, TaskPriority $task_priority): TaskPriorityResource
    {
        return new TaskPriorityResource($this->priorities->update($task_priority, $request->validated()));
    }

    public function destroy(TaskPriority $task_priority): JsonResponse
    {
        $this->priorities->delete($task_priority);

        return response()->json(null, 204);
    }
}
