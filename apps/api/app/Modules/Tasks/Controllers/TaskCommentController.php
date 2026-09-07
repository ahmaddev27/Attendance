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
        return TaskCommentResource::collection($this->comments->threadForTask($task));
    }

    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

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
}
