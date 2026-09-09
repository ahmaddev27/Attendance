<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Resources;

use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskComment
 */
class TaskCommentResource extends JsonResource
{
    /**
     * Window within which an author may edit their own comment. Comments
     * older than this stay visible but the FE hides the edit control.
     */
    private const EDIT_WINDOW_MINUTES = 15;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authId = auth()->id();
        $authUser = auth()->user();
        $isAuthor = $authId !== null && $authId === $this->user_id;
        $withinEditWindow = $this->created_at !== null
            && $this->created_at->diffInMinutes() < self::EDIT_WINDOW_MINUTES;

        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'parent_id' => $this->parent_id,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->employee?->avatar_url,
            ]),
            'body' => $this->body,
            'mentions' => $this->mentions ?? [],
            'edited_at' => $this->edited_at,
            'can_edit' => $isAuthor && $withinEditWindow,
            'can_delete' => $isAuthor
                || ($authUser?->hasPermissionTo('manage-workflows') ?? false),
            'replies' => self::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
