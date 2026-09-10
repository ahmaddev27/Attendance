<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Modules\Tasks\Requests\StoreTaskRequest;
use App\Modules\Tasks\Requests\UpdateTaskRequest;
use App\Modules\Tasks\Resources\TaskDetailResource;
use App\Modules\Tasks\Resources\TaskResource;
use App\Modules\Tasks\Resources\TaskStatusResource;
use App\Modules\Tasks\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'assigned_to', 'created_by', 'status_id', 'priority_id',
            'tag_id', 'search', 'due_date_from', 'due_date_to',
            'parent_task_id', 'sort',
        ]);

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        /** @var User $actor */
        $actor = $request->user();

        return TaskResource::collection($this->taskService->paginate($filters, $perPage, $actor));
    }

    /**
     * Grouped by status.code for a Kanban board — every configured status
     * is represented, including ones with zero matching tasks.
     */
    public function kanban(Request $request): JsonResponse
    {
        $filters = $request->only(['assigned_to', 'created_by', 'priority_id', 'tag_id', 'search']);

        /** @var User $actor */
        $actor = $request->user();

        $columns = $this->taskService->kanban($filters, $actor)->map(fn (array $entry) => [
            'status' => new TaskStatusResource($entry['status']),
            'tasks' => TaskResource::collection($entry['tasks']),
            // count_total > tasks.length signals the column was capped —
            // the frontend uses the delta to render "+ N more".
            'count_total' => $entry['count_total'],
        ]);

        return response()->json(['data' => $columns]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $task = $this->taskService->create($request->validated(), $actor);

        return (new TaskResource($this->taskService->find($task->id)))->response()->setStatusCode(201);
    }

    public function show(Request $request, Task $task): TaskDetailResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new TaskDetailResource($this->taskService->findFor($actor, $task->id));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        /** @var User $actor */
        $actor = $request->user();

        $updated = $this->taskService->update($task, $request->validated(), $actor);

        return new TaskResource($this->taskService->find($updated->id));
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->taskService->delete($task, $actor);

        return response()->json(null, 204);
    }

    /**
     * Deliberately typed as `int` rather than an implicitly bound `Task`
     * — Laravel's default route-model binding excludes soft-deleted
     * rows, which is exactly the record this endpoint needs to find.
     */
    public function restore(Request $request, int $task): TaskResource
    {
        /** @var User $actor */
        $actor = $request->user();

        $restored = $this->taskService->restore($task, $actor);

        return new TaskResource($this->taskService->find($restored->id));
    }

    public function complete(Request $request, Task $task): TaskResource
    {
        /** @var User $actor */
        $actor = $request->user();

        $completed = $this->taskService->complete($task, $actor);

        return new TaskResource($this->taskService->find($completed->id));
    }
}
