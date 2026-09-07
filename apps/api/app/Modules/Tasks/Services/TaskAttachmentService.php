<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\User;
use App\Shared\Enums\TaskAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * File-type/size validation happens in UploadTaskAttachmentRequest, before
 * a request ever reaches this service — this only handles the actual
 * storage (delegated entirely to Spatie MediaLibrary; see
 * Task::registerMediaCollections()) and the accompanying history entry.
 */
class TaskAttachmentService
{
    private const DOWNLOAD_LINK_LIFETIME_MINUTES = 30;

    public function __construct(
        private readonly TaskHistoryService $history,
    ) {}

    public function upload(Task $task, UploadedFile $file, User $user): Media
    {
        return DB::transaction(function () use ($task, $file, $user) {
            $media = $task->addMedia($file)
                ->withCustomProperties(['uploaded_by' => $user->id])
                ->toMediaCollection('attachments');

            $this->history->log($task, $user, TaskAction::AttachedFile, null, [
                'filename' => $media->file_name,
                'size' => $media->size,
            ]);

            return $media;
        });
    }

    public function delete(Media $media): void
    {
        $media->delete();
    }

    /**
     * A time-limited signed URL rather than a permanent public one — the
     * underlying disk (MinIO in production) is not otherwise
     * authenticated, so anyone with a stale link should eventually lose
     * access to it.
     */
    public function signedDownloadUrl(Media $media): string
    {
        return URL::temporarySignedRoute(
            'tasks.attachments.download',
            now()->addMinutes(self::DOWNLOAD_LINK_LIFETIME_MINUTES),
            ['media' => $media->id],
        );
    }
}
