<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Resources;

use App\Models\Task;
use App\Shared\Enums\TaskEntityType;
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
            // creator/assignee use the full EmployeeSummary shape (id,
            // employee_number, full_name, avatar_url) so the FE avatar
            // component can render a real image instead of falling back to
            // initials, and the number column has a value to show.
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'employee_number' => $this->creator->employee_number,
                'full_name' => $this->creator->full_name,
                'avatar_url' => $this->creator->avatar_url,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => $this->assignee->id,
                'employee_number' => $this->assignee->employee_number,
                'full_name' => $this->assignee->full_name,
                'avatar_url' => $this->assignee->avatar_url,
            ]),
            'tags' => TaskTagResource::collection($this->whenLoaded('tags')),

            // `entity_label` is a read-side attribute stamped by
            // TaskEntityLabeler in one query per entity type — never a
            // column, never persisted.
            'entity' => $this->entity_type instanceof TaskEntityType && $this->entity_id !== null ? [
                'type' => $this->entity_type->value,
                'id' => $this->entity_id,
                'label' => $this->getAttribute('entity_label'),
                'link' => $this->entity_type->route((int) $this->entity_id),
            ] : null,

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
