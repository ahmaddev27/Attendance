<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tasks\Events\TaskCreated;
use App\Modules\Tasks\Events\TaskUpdated;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Repositories\TaskStatusRepository;
use App\Shared\Enums\TaskAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the task lifecycle: create, field updates (with history
 * tracking for every meaningful change), completion, soft delete, and
 * restore. Every state-changing method runs inside a DB transaction so a
 * task update and its history entries are never left half-committed.
 */
class TaskService
{
    /**
     * Fields whose change is worth a dedicated TaskHistory row. Anything
     * else UpdateTaskRequest accepts (title, description, estimated_hours,
     * ...) is still persisted but not individually audited.
     *
     * @var list<string>
     */
    private const TRACKED_FIELDS = ['status_id', 'priority_id', 'assigned_to', 'due_date', 'progress_percent'];

    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly TaskStatusRepository $statuses,
        private readonly TaskHistoryService $history,
        private readonly NotificationService $notifier,
        private readonly TaskEntityLabeler $entityLabels,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25, ?User $actor = null): LengthAwarePaginator
    {
        $page = $this->tasks->paginate(
            $filters,
            $perPage,
            ownedByEmployeeId: $this->authorizedScopeFor($actor),
        );

        $this->entityLabels->hydrate($page->items());

        return $page;
    }

    /**
     * Every status (in board order), each carrying up to
     * TaskRepository::KANBAN_COLUMN_LIMIT tasks matching $filters plus
     * the unlimited `count_total` for that column. Statuses with no
     * matching tasks are still emitted so the board can render every
     * column, and the count_total lets the UI render a "+ N more"
     * affordance when the visible slice is short of the true total.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<string, array{status: TaskStatus, tasks: Collection<int, Task>, count_total: int}>
     */
    public function kanban(array $filters = [], ?User $actor = null): Collection
    {
        $grouped = $this->tasks->groupByStatus($filters, $this->authorizedScopeFor($actor));

        $this->entityLabels->hydrate($grouped->flatMap(fn (array $entry) => $entry['tasks']));

        return $this->statuses->all()
            ->keyBy(fn (TaskStatus $status) => $status->code)
            ->map(function (TaskStatus $status) use ($grouped) {
                $entry = $grouped->get($status->code);

                return [
                    'status' => $status,
                    'tasks' => $entry['tasks'] ?? new Collection(),
                    'count_total' => $entry['count_total'] ?? 0,
                ];
            });
    }

    public function find(int $id): Task
    {
        $task = $this->tasks->findOrFail($id);

        $this->entityLabels->hydrate([$task]);

        return $task;
    }

    /**
     * Authorized single-task read. Applies the same access rule as
     * assertCanActOnTask (admin OR creator/assignee) — used by the
     * show endpoint to block cross-employee reads by id.
     */
    public function findFor(User $actor, int $id): Task
    {
        $task = $this->tasks->findOrFail($id);

        $this->assertCanActOnTask($task, $actor);
        $this->entityLabels->hydrate([$task]);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Task
    {
        // Server-owned attribution. Admins (manage-workflows) may create a
        // task on behalf of another employee by supplying `created_by`
        // explicitly — the ops/manager creation flow relies on it. For
        // everyone else, `created_by` is forced to the acting employee so
        // a compromised token can't spoof authorship.
        $isAdmin = $this->actorHasManageWorkflows($actor);

        if (! $isAdmin || empty($data['created_by'])) {
            // Author = the actor's employee, full stop. Falling back to
            // the ASSIGNEE (as the previous version did) was actively
            // wrong: it flipped "admin created this and assigned it to
            // Ahmed" into "Ahmed created this and assigned it to himself",
            // which made Ahmed's "Created by me" tab and audit trail lie.
            //
            // Admin actors without a linked employee are allowed to
            // create tasks with created_by = null (nullable column via
            // migration 2026_09_20_100003) — the UI renders that as
            // "النظام / الإدارة" so the true authorship is preserved.
            $fallback = $actor->employee_id;

            if ($fallback === null && ! $isAdmin) {
                throw ValidationException::withMessages([
                    'created_by' => 'حسابك غير مرتبط بموظف — تواصل مع الإدارة لربط حسابك.',
                ]);
            }

            $data['created_by'] = $fallback;
        }
        $createdBy = $data['created_by'] !== null ? (int) $data['created_by'] : null;

        // Non-admins can only assign to teammates (same team_id) — this
        // prevents a rogue employee from spamming tasks to strangers or
        // using cross-team assignment to probe the org chart. Admins with
        // manage-workflows keep the full-org reach.
        if (! $isAdmin && ! empty($data['assigned_to'])) {
            $me = $actor->employee;
            $assigneeTeamId = \App\Models\Employee::query()
                ->whereKey($data['assigned_to'])
                ->value('team_id');

            $selfAssign = (int) $data['assigned_to'] === (int) ($me?->id);
            $sameTeam = $me?->team_id !== null && $me->team_id === $assigneeTeamId;

            if (! $selfAssign && ! $sameTeam) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'يمكنك إسناد المهمة لأعضاء فريقك فقط.',
                ]);
            }
        }

        // Subtask attachment IDOR guard: `exists:tasks,id` in the request
        // rule proves the parent exists but not that the caller may reach
        // it. Reuse the update/delete access rule (admin OR creator/assignee
        // of the PARENT task) so an employee can't graft a subtask onto
        // someone else's work.
        if (! empty($data['parent_task_id'])) {
            $parent = $this->tasks->findOrFail((int) $data['parent_task_id']);

            if (! $this->canActOnTask($parent, $actor)) {
                throw ValidationException::withMessages([
                    'parent_task_id' => 'لا تملك صلاحية إضافة مهمة فرعية إلى هذه المهمة الأم.',
                ]);
            }
        }

        $statusId = $data['status_id'] ?? $this->statuses->firstBySortOrder()?->id;

        if (empty($statusId)) {
            throw ValidationException::withMessages([
                'status_id' => 'No task status is configured to default to. Seed at least one task status first.',
            ]);
        }

        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        $task = DB::transaction(function () use ($data, $createdBy, $statusId, $tags, $actor) {
            $task = $this->tasks->create([
                ...$data,
                'created_by' => $createdBy,
                'status_id' => $statusId,
            ]);

            if ($tags !== null) {
                $task->tags()->sync($tags);
                $task->load('tags');
            }

            $this->history->log($task, $actor, TaskAction::Created);

            if (! empty($data['assigned_to'])) {
                $this->history->log($task, $actor, TaskAction::Assigned, null, ['assignee_id' => $data['assigned_to']]);
            }

            return $task;
        });

        TaskCreated::dispatch($task);

        if (! empty($data['assigned_to'])) {
            $this->notifier->taskAssigned($task->fresh(['assignee.user']));
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data, User $actor): Task
    {
        $this->assertCanActOnTask($task, $actor);

        // Same team-scope guard as create: non-admins can reassign only
        // within their own team (or to themselves). Admins with
        // manage-workflows are unrestricted.
        if (
            array_key_exists('assigned_to', $data)
            && ! empty($data['assigned_to'])
            && ! $this->actorHasManageWorkflows($actor)
        ) {
            $me = $actor->employee;
            $assigneeTeamId = \App\Models\Employee::query()
                ->whereKey($data['assigned_to'])
                ->value('team_id');

            $selfAssign = (int) $data['assigned_to'] === (int) ($me?->id);
            $sameTeam = $me?->team_id !== null && $me->team_id === $assigneeTeamId;

            if (! $selfAssign && ! $sameTeam) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'يمكنك إسناد المهمة لأعضاء فريقك فقط.',
                ]);
            }
        }

        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        $changes = $this->detectTrackedChanges($task, $data);

        $updated = DB::transaction(function () use ($task, $data, $changes, $tags, $actor) {
            $updated = $this->tasks->update($task, $data);

            if ($tags !== null) {
                $updated->tags()->sync($tags);
                $updated->load('tags');
            }

            foreach ($changes as $field => [$old, $new]) {
                $this->history->log(
                    $updated,
                    $actor,
                    $this->actionForFieldChange($field, $new),
                    [$field => $old],
                    [$field => $new],
                );
            }

            if (array_key_exists('status_id', $changes) && $updated->completed_at === null) {
                $newStatus = $this->statuses->findOrFail($changes['status_id'][1]);

                if ($newStatus->is_done_state) {
                    $updated = $this->tasks->update($updated, ['completed_at' => now()]);
                }
            }

            return $updated;
        });

        TaskUpdated::dispatch($updated, $changes);

        // A reassignment (assigned_to change to a non-null value) triggers a
        // notification to the new assignee. Skips no-op updates and unassigns.
        if (array_key_exists('assigned_to', $changes) && $changes['assigned_to'][1] !== null) {
            $this->notifier->taskAssigned($updated->fresh(['assignee.user']));
        }

        return $updated;
    }

    /**
     * Shortcut for the "mark done" action: moves the task to the first
     * done-state status (by sort_order) and stamps completed_at.
     */
    public function complete(Task $task, User $actor): Task
    {
        $this->assertCanActOnTask($task, $actor);

        $doneStatus = $this->statuses->firstDoneState();

        if ($doneStatus === null) {
            throw ValidationException::withMessages([
                'status_id' => 'No done-state task status is configured.',
            ]);
        }

        $completed = DB::transaction(function () use ($task, $doneStatus, $actor) {
            $locked = $this->tasks->findForUpdate($task->id);
            $oldStatusId = $locked->status_id;
            $completedAt = $locked->completed_at ?? now();

            $updated = $this->tasks->update($locked, [
                'status_id' => $doneStatus->id,
                'progress_percent' => 100,
                'completed_at' => $completedAt,
            ]);

            $this->history->log(
                $updated,
                $actor,
                TaskAction::Completed,
                ['status_id' => $oldStatusId],
                ['status_id' => $doneStatus->id, 'completed_at' => $completedAt->toIso8601String()],
            );

            return $updated;
        });

        TaskUpdated::dispatch($completed, ['status_id' => [$task->status_id, $doneStatus->id]]);

        return $completed;
    }

    public function delete(Task $task, User $actor): void
    {
        $this->assertCanActOnTask($task, $actor);

        DB::transaction(function () use ($task, $actor) {
            $this->tasks->delete($task);
            $this->history->log($task, $actor, TaskAction::Deleted);
        });
    }

    public function restore(int $id, User $actor): Task
    {
        $trashed = $this->tasks->findTrashedOrFail($id);

        return DB::transaction(function () use ($trashed, $actor) {
            $restored = $this->tasks->restore($trashed);
            $this->history->log($restored, $actor, TaskAction::Restored);

            return $restored;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function detectTrackedChanges(Task $task, array $data): array
    {
        $changes = [];

        foreach (self::TRACKED_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $old = $task->getAttribute($field);
            $new = $data[$field];

            $normalizedOld = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old;

            if ($normalizedOld != $new) { // loose: DB may return string vs int/null
                $changes[$field] = [$normalizedOld, $new];
            }
        }

        return $changes;
    }

    private function actionForFieldChange(string $field, mixed $newValue): TaskAction
    {
        return match ($field) {
            'status_id' => TaskAction::StatusChanged,
            'priority_id' => TaskAction::PriorityChanged,
            'assigned_to' => $newValue === null ? TaskAction::Unassigned : TaskAction::Assigned,
            default => TaskAction::Updated,
        };
    }

    /**
     * Guard the state-changing task actions (update, delete, complete)
     * against cross-employee tampering. The acting user is authorized only
     * if they hold `manage-workflows` OR the task is their own (creator or
     * assignee). Everyone else 403s — before the audit fix any authenticated
     * user could PUT/DELETE any task in the org by id.
     */
    private function assertCanActOnTask(Task $task, User $actor): void
    {
        if ($this->canActOnTask($task, $actor)) {
            return;
        }

        abort(403, 'You do not have permission to act on this task.');
    }

    /**
     * Boolean form of the access rule enforced by assertCanActOnTask —
     * used where the caller needs to translate a denial into something
     * other than a 403 (e.g. a ValidationException for parent_task_id).
     */
    private function canActOnTask(Task $task, User $actor): bool
    {
        if ($this->actorHasManageWorkflows($actor)) {
            return true;
        }

        $employeeId = $actor->employee_id;

        return $employeeId !== null
            && ((int) $task->created_by === (int) $employeeId
                || (int) $task->assigned_to === (int) $employeeId);
    }

    /**
     * Employee-id scope for list/kanban visibility. Admins with
     * manage-workflows see everything (null = no scope); everyone else is
     * pinned to tasks they created or were assigned. Returns null when the
     * acting user has no linked employee profile either — the caller
     * should treat that as "no visible tasks" (see repository::paginate).
     */
    private function authorizedScopeFor(?User $actor): ?int
    {
        if ($actor === null || $this->actorHasManageWorkflows($actor)) {
            return null;
        }

        return $actor->employee_id;
    }

    /**
     * Spatie throws PermissionDoesNotExist when the permission row is
     * missing from the DB (typical in tests that don't run the seeder,
     * and in fresh installs before RolePermissionSeeder has run). A
     * missing permission is semantically equivalent to "user does not
     * have it", so swallow the exception and return false — throwing
     * on a routine capability check would 500 every request from a
     * regular employee on any not-yet-seeded install.
     */
    private function actorHasManageWorkflows(?User $actor): bool
    {
        if ($actor === null || ! method_exists($actor, 'hasPermissionTo')) {
            return false;
        }

        try {
            return $actor->hasPermissionTo('manage-workflows');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }
}
