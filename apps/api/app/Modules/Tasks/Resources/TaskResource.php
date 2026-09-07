<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The list/summary shape of a task — nested relations reduced to just
 * what a task row/card needs, so index/kanban responses stay light.
 * TaskDetailResource extends this with everything the single-task view
 * needs on top.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_task_id' => $this->parent_task_id,
            'title' => $this->title,
            'description' => $this->description,

            'status' => $this->whenLoaded('status', fn () => $this->status === null ? null : [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'code' => $this->status->code,
                'color' => $this->status->color,
            ]),
            'priority' => $this->whenLoaded('priority', fn () => $this->priority === null ? null : [
                'id' => $this->priority->id,
                'name' => $this->priority->name,
                'code' => $this->priority->code,
                'color' => $this->priority->color,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'full_name' => $this->assignee->full_name,
            ]),
            'tags' => TaskTagResource::collection($this->whenLoaded('tags')),

            'comments_count' => $this->when(isset($this->comments_count), fn () => (int) $this->comments_count),
            'attachments_count' => $this->when(isset($this->attachments_count), fn () => (int) $this->attachments_count),

            'estimated_hours' => $this->estimated_hours === null ? null : (float) $this->estimated_hours,
            'actual_hours' => $this->actual_hours === null ? null : (float) $this->actual_hours,
            'progress_percent' => $this->progress_percent,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
