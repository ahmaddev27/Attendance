<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Resources;

use Illuminate\Http\Request;

/**
 * The single-task view: everything TaskResource has, plus subtasks, the
 * top level of the comment thread (see TaskRepository::findOrFail()),
 * the full history log, and attachments with ready-to-use signed
 * download URLs.
 */
class TaskDetailResource extends TaskResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent === null ? null : [
                'id' => $this->parent->id,
                'title' => $this->parent->title,
            ]),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'history' => TaskHistoryResource::collection($this->whenLoaded('history')),
            'attachments' => TaskAttachmentResource::collection($this->whenLoaded('media')),
        ];
    }
}
