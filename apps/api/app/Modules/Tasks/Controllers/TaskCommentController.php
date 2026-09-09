<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Modules\Tasks\Requests\StoreTaskCommentRequest;
use App\Modules\Tasks\Requests\UpdateTaskCommentRequest;
use App\Modules\Tasks\Resources\TaskCommentResource;
use App\Modules\Tasks\Services\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskCommentController extends Controller
{
    public function __construct(
        private readonly TaskCommentService $comments,
    ) {}

    /**
     * The full comment thread for a task: top-level comments newest
     * first, each carrying its nested replies.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        /** @var User|null $user */
        $user = request()->user();
        $this->assertCanAccessTask($task, $user);

        return TaskCommentResource::collection($this->comments->threadForTask($task));
    }

    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessTask($task, $user);

        $comment = $this->comments->create(
            $task,
            $user,
            (string) $request->validated('body'),
            $request->validated('mentions'),
            $request->validated('parent_id'),
        );

        return (new TaskCommentResource($comment))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskCommentRequest $request, TaskComment $comment): TaskCommentResource
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $this->comments->update(
            $comment,
            $user,
            (string) $request->validated('body'),
            $request->validated('mentions'),
        );

        return new TaskCommentResource($updated);
    }

    public function destroy(Request $request, TaskComment $comment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->comments->delete($comment, $user);

        return response()->json(null, 204);
    }

    /**
     * Common comment-thread gate: admins (manage-workflows) get through
     * for every task; everyone else only for tasks they created or were
     * assigned to. Before this check, `/tasks/{task}/comments` was an
     * IDOR — any employee could read/write every task's discussion thread.
     */
    private function assertCanAccessTask(Task $task, ?User $user): void
    {
        abort_unless($user !== null, 401);

        if ($user->hasPermissionTo('manage-workflows')) {
            return;
        }

        $employeeId = $user->employee_id;

        if ($employeeId !== null
            && ((int) $task->created_by === (int) $employeeId
                || (int) $task->assigned_to === (int) $employeeId)
        ) {
            return;
        }

        abort(403, 'You do not have permission to access this task.');
    }
}
