<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Repositories;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskRepository
{
    /**
     * Relations eager-loaded on every read so the resource layer never
     * triggers an N+1 query per row.
     *
     * @var list<string>
     */
    private const WITH = ['status', 'priority', 'creator', 'assignee', 'tags'];

    /**
     * @var list<string>
     */
    private const WITH_COUNTS = ['comments as comments_count', 'media as attachments_count'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters);

        return $this->applySort($query, $filters)->paginate($perPage);
    }

    /**
     * Every task matching $filters, grouped by its status code — the
     * shape the Kanban board needs. Not paginated: a board view is
     * expected to render every column in one shot.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<string, Collection<int, Task>>
     */
    public function groupByStatus(array $filters = []): Collection
    {
        return $this->applySort($this->baseQuery($filters), $filters)
            ->get()
            ->groupBy(fn (Task $task) => $task->status->code);
    }

    /**
     * Full detail view for a single task. `comments` is constrained to
     * top-level rows (parent_id null) with one level of replies nested
     * underneath — the depth a task's own detail payload renders inline;
     * TaskCommentController::index() goes through
     * TaskCommentRepository::threadForTask() instead when a caller needs
     * the fully reconstructed (arbitrarily deep) thread on its own.
     */
    public function findOrFail(int $id): Task
    {
        return Task::query()
            ->with([
                ...self::WITH,
                'parent',
                'subtasks.status',
                'subtasks.priority',
                // Not type-hinted as Builder: Eloquent invokes this
                // constraint closure with the Relation instance itself
                // (HasMany here), not a plain query builder.
                'comments' => fn ($query) => $query->whereNull('parent_id'),
                'comments.user',
                'comments.replies.user',
                'history.user',
                'media',
            ])
            ->withCount(self::WITH_COUNTS)
            ->findOrFail($id);
    }

    /**
     * Re-fetches the task with a row lock, for use inside a transaction
     * that is about to transition its state — same lockForUpdate-inside-
     * transaction pattern LeaveRequestRepository uses.
     */
    public function findForUpdate(int $id): Task
    {
        return Task::query()->lockForUpdate()->findOrFail($id);
    }

    public function findTrashedOrFail(int $id): Task
    {
        return Task::onlyTrashed()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Task
    {
        return Task::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task->refresh()->load(self::WITH);
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function restore(Task $task): Task
    {
        $task->restore();

        return $task->refresh()->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Task::query()->with(self::WITH)->withCount(self::WITH_COUNTS);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['assigned_to', 'created_by', 'status_id', 'priority_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn (Builder $q) => $q->where('task_tags.id', $filters['tag_id']));
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['due_date_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_date_from']);
        }

        if (! empty($filters['due_date_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_date_to']);
        }

        // Present-but-empty (including the literal string "null") means
        // "top-level tasks only"; a numeric value scopes to the subtasks
        // of that parent; the key being entirely absent leaves both
        // top-level tasks and subtasks in the result set.
        if (array_key_exists('parent_task_id', $filters)) {
            $value = $filters['parent_task_id'];

            if ($value === null || $value === '' || $value === 'null') {
                $query->whereNull('parent_task_id');
            } else {
                $query->where('parent_task_id', $value);
            }
        }
    }

    /**
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    private function applySort(Builder $query, array $filters): Builder
    {
        $sort = $filters['sort'] ?? 'created_at';

        return match ($sort) {
            'due_date' => $query->orderByRaw('due_date IS NULL')->orderBy('due_date'),
            'priority' => $query->join('task_priorities', 'task_priorities.id', '=', 'tasks.priority_id')
                ->orderBy('task_priorities.sort_order')
                ->select('tasks.*'),
            default => $query->orderByDesc('created_at'),
        };
    }
}
