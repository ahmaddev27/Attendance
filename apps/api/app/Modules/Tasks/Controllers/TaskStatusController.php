<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskStatus;
use App\Modules\Tasks\Requests\StoreTaskStatusRequest;
use App\Modules\Tasks\Requests\UpdateTaskStatusRequest;
use App\Modules\Tasks\Resources\TaskStatusResource;
use App\Modules\Tasks\Services\TaskStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskStatusController extends Controller
{
    public function __construct(
        private readonly TaskStatusService $statuses,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return TaskStatusResource::collection($this->statuses->all());
    }

    public function store(StoreTaskStatusRequest $request): JsonResponse
    {
        $status = $this->statuses->create($request->validated());

        return (new TaskStatusResource($status))->response()->setStatusCode(201);
    }

    public function show(TaskStatus $task_status): TaskStatusResource
    {
        return new TaskStatusResource($task_status);
    }

    public function update(UpdateTaskStatusRequest $request, TaskStatus $task_status): TaskStatusResource
    {
        return new TaskStatusResource($this->statuses->update($task_status, $request->validated()));
    }

    public function destroy(TaskStatus $task_status): JsonResponse
    {
        $this->statuses->delete($task_status);

        return response()->json(null, 204);
    }
}
