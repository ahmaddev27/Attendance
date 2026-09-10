<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Repositories;

use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskRepository
{
    /**
     * Hard cap on the number of task rows returned per Kanban column.
     * Anything beyond this stays counted (see `count_total` in the
     * groupByStatus() payload) but isn't shipped in the initial render —
     * a status with 2000 stale "todo" cards used to serialise every row
     * on every board load. 200 fits several scroll pages and leaves a
     * clear "+ N more" affordance for the frontend.
     */
    private const KANBAN_COLUMN_LIMIT = 200;

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
     * @param  int|null              $ownedByEmployeeId  When non-null, force-scopes
     *                                                    the query to tasks that
     *                                                    the given employee created
     *                                                    OR is assigned to. Callers
     *                                                    without `manage-workflows`
     *                                                    pass their own employee id
     *                                                    to block cross-employee
     *                                                    reads (Tasks IDOR fix).
     */
    public function paginate(array $filters, int $perPage, ?int $ownedByEmployeeId = null): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters);

        if ($ownedByEmployeeId !== null) {
            $query->where(function (Builder $q) use ($ownedByEmployeeId): void {
                $q->where('created_by', $ownedByEmployeeId)
                    ->orWhere('assigned_to', $ownedByEmployeeId);
            });
        }

        return $this->applySort($query, $filters)->paginate($perPage);
    }

    /**
     * Tasks matching $filters grouped by status code, capped at
     * KANBAN_COLUMN_LIMIT rows per column. The old implementation loaded
     * every task in one shot and grouped in PHP — a board with a large
     * backlog would return tens of thousands of rows on each request.
     *
     * Each entry carries the top-N cards (ordered by `updated_at DESC`,
     * so recently-touched work stays visible) plus `count_total`, the
     * unlimited number of tasks in that column. The frontend uses the
     * gap between `tasks.length` and `count_total` to render a "+ N
     * more" affordance.
     *
     * @param  array<string, mixed>  $filters
     * @param  int|null              $ownedByEmployeeId  When non-null, force-scopes
     *                                                    both the count and the tasks
     *                                                    query to rows the employee
     *                                                    created or is assigned to
     *                                                    (Tasks kanban IDOR fix — the
     *                                                    board used to leak every task
     *                                                    in the org to any employee).
     * @return Collection<string, array{tasks: Collection<int, Task>, count_total: int}>
     */
    public function groupByStatus(array $filters = [], ?int $ownedByEmployeeId = null): Collection
    {
        $statuses = TaskStatus::query()->orderBy('sort_order')->get();
        $result = new Collection();

        $applyOwnership = static function (Builder $query) use ($ownedByEmployeeId): void {
            if ($ownedByEmployeeId === null) {
                return;
            }

            $query->where(function (Builder $w) use ($ownedByEmployeeId): void {
                $w->where('created_by', $ownedByEmployeeId)
                    ->orWhere('assigned_to', $ownedByEmployeeId);
            });
        };

        foreach ($statuses as $status) {
            // Lean count query — no eager loads, no withCount subqueries —
            // so the total lookup stays a single COUNT(*) per column.
            $countQuery = Task::query()->where('status_id', $status->id);
            $this->applyFilters($countQuery, $filters);
            $applyOwnership($countQuery);
            $total = $countQuery->count();

            $tasksQuery = $this->baseQuery($filters)->where('status_id', $status->id);
            $applyOwnership($tasksQuery);
            $tasks = $tasksQuery
                ->orderByDesc('updated_at')
                ->limit(self::KANBAN_COLUMN_LIMIT)
                ->get();

            $result->put($status->code, [
                'tasks' => $tasks,
                'count_total' => $total,
            ]);
        }

        return $result;
    }

    /**
     * Full detail view for a single task. `comments` is constrained to
     * top-level rows (parent_id null), capped at the 50 most recent, so
     * a task with hundreds of comments never ships a multi-MB payload on
     * initial load. Nested replies are NOT eager-loaded here — the FE
     * fetches the fully reconstructed thread lazily via
     * `TaskCommentController::index` (GET /tasks/{task}/comments), which
     * goes through TaskCommentRepository::threadForTask() and returns
     * every level of nesting on demand. `history` is likewise capped at
     * the 30 most recent entries to keep the audit payload bounded.
     */
    public function findOrFail(int $id): Task
    {
        return Task::query()
            ->with([
                ...self::WITH,
                'parent',
                'subtasks.status',
                'subtasks.priority',
                // Not type-hinted as Builder: Eloquent invokes these
                // constraint closures with the Relation instance itself
                // (HasMany here), not a plain query builder.
                'comments' => fn ($query) => $query
                    ->whereNull('parent_id')
                    ->latest()
                    ->limit(50),
                'comments.user',
                'history' => fn ($query) => $query->latest()->limit(30),
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

        // due_date is a DATE column — bare where() so the tasks(due_date)
        // index actually gets used (DATE() wrappers would disqualify it).
        if (! empty($filters['due_date_from'])) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }

        if (! empty($filters['due_date_to'])) {
            $query->where('due_date', '<=', $filters['due_date_to']);
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
