<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Modules\Tasks\Requests\UploadTaskAttachmentRequest;
use App\Modules\Tasks\Resources\TaskAttachmentResource;
use App\Modules\Tasks\Services\TaskAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function __construct(
        private readonly TaskAttachmentService $attachments,
    ) {}

    public function index(Task $task): AnonymousResourceCollection
    {
        /** @var User|null $user */
        $user = request()->user();
        $this->assertCanAccessTask($task, $user);

        return TaskAttachmentResource::collection($task->getMedia('attachments'));
    }

    public function upload(UploadTaskAttachmentRequest $request, Task $task): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->assertCanAccessTask($task, $user);

        $media = $this->attachments->upload($task, $request->file('file'), $user);

        return (new TaskAttachmentResource($media))->response()->setStatusCode(201);
    }

    /**
     * The route this action serves is registered with the `signed`
     * middleware (see routes/api.php) — an invalid or expired signature
     * never reaches this method at all, so the signature itself is what
     * authorizes the download.
     */
    public function download(Media $media): StreamedResponse
    {
        return Storage::disk($media->disk)->download($media->getPathRelativeToRoot(), $media->file_name);
    }

    public function destroy(Media $media): JsonResponse
    {
        // Ownership check: the Media row must belong to a Task, and the
        // requester must be able to delete THAT task's attachments.
        // Without this, any authenticated user could DELETE any Media
        // row in the system by id (task attachments today, potentially
        // any other morph relation tomorrow).
        abort_unless($media->model_type === \App\Models\Task::class, 404);

        /** @var \App\Models\Task|null $task */
        $task = $media->model()->first();
        abort_unless($task !== null, 404);

        /** @var User|null $user */
        $user = request()->user();
        $this->assertCanAccessTask($task, $user);

        $this->attachments->delete($media);

        return response()->json(null, 204);
    }

    /**
     * Common attachment-list gate: admins (manage-workflows) get through
     * for every task; everyone else only for tasks they created or were
     * assigned to. Before this check, `/tasks/{task}/attachments` was an
     * IDOR — any employee could enumerate every task's files.
     */
    private function assertCanAccessTask(Task $task, ?User $user): void
    {
        abort_unless($user !== null, 401);

        // Wrap in try/catch: Spatie throws PermissionDoesNotExist when
        // the permission isn't seeded (bare install, some tests).
        // Missing permission == user does not have it.
        try {
            if ($user->hasPermissionTo('manage-workflows')) {
                return;
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            // fall through to ownership check
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
