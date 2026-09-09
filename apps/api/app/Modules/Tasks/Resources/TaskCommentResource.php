<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Resources;

use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

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
                || $this->safeHasPermission($authUser, 'manage-workflows'),
            'replies' => self::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Spatie throws PermissionDoesNotExist when the row isn't seeded
     * (fresh install, some tests) — a resource being serialised must
     * never break the response for that. Semantically: missing
     * permission == user does not have it.
     */
    private function safeHasPermission(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }
        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
