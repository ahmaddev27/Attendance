<?php

declare(strict_types=1);

namespace App\Modules\Search\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Cross-index search over the four indexed models. Fans a single search
 * term out to each Scout index in parallel-friendly per-model queries
 * and returns a flat, uniformly-shaped result set the header palette can
 * render without knowing which type it's iterating.
 */
class GlobalSearchService
{
    public const DEFAULT_LIMIT_PER_TYPE = 5;

    /**
     * @return array{
     *     employees: array<int, array{type: string, id: int, title: string, subtitle: string, url: string}>,
     *     tasks: array<int, array{type: string, id: int, title: string, subtitle: string, url: string}>,
     *     requests: array<int, array{type: string, id: int, title: string, subtitle: string, url: string}>,
     *     leaves: array<int, array{type: string, id: int, title: string, subtitle: string, url: string}>,
     * }
     */
    public function query(string $term, int $limitPerType = self::DEFAULT_LIMIT_PER_TYPE): array
    {
        $term = trim($term);

        if ($term === '') {
            return [
                'employees' => [],
                'tasks' => [],
                'requests' => [],
                'leaves' => [],
            ];
        }

        return [
            'employees' => $this->searchEmployees($term, $limitPerType),
            'tasks' => $this->searchTasks($term, $limitPerType),
            'requests' => $this->searchRequests($term, $limitPerType),
            'leaves' => $this->searchLeaves($term, $limitPerType),
        ];
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, subtitle: string, url: string}>
     */
    private function searchEmployees(string $term, int $limit): array
    {
        return Employee::search($term)
            ->take($limit)
            ->get()
            ->map(fn (Employee $employee) => [
                'type' => 'employee',
                'id' => $employee->id,
                'title' => $employee->full_name,
                'subtitle' => (string) $employee->employee_number,
                'url' => "/employees/{$employee->id}",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, subtitle: string, url: string}>
     */
    private function searchTasks(string $term, int $limit): array
    {
        return Task::search($term)
            ->take($limit)
            ->get()
            ->map(fn (Task $task) => [
                'type' => 'task',
                'id' => $task->id,
                'title' => (string) $task->title,
                'subtitle' => $this->truncate((string) $task->description, 80),
                'url' => "/tasks/{$task->id}",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, subtitle: string, url: string}>
     */
    private function searchRequests(string $term, int $limit): array
    {
        $query = RequestModel::search($term);

        // Non-privileged callers see ONLY their own requests. Before this
        // scoping, `/api/search?q=...` leaked request_numbers (and, via
        // the leaves branch, leave reasons) belonging to any employee to
        // any authenticated user.
        //
        // ->query() is used (instead of ->where()) so the scoping runs on
        // the SQL hydration query rather than as a Meilisearch filter —
        // this keeps the fix independent of whether `employee_id` is
        // configured as a filterable attribute in the search index.
        $ownEmployeeId = $this->ownEmployeeIdIfScoped();
        if ($ownEmployeeId !== null) {
            $query->query(fn ($q) => $q->where('employee_id', $ownEmployeeId));
        }

        return $query
            ->take($limit)
            ->get()
            ->load('requestType')
            ->map(fn (RequestModel $request) => [
                'type' => 'request',
                'id' => $request->id,
                'title' => (string) ($request->requestType?->name ?? $request->request_number),
                'subtitle' => (string) $request->request_number,
                'url' => "/requests/{$request->id}",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, id: int, title: string, subtitle: string, url: string}>
     */
    private function searchLeaves(string $term, int $limit): array
    {
        $query = LeaveRequest::search($term);

        // Same rationale as searchRequests: scope via ->query() so this
        // works regardless of the Meilisearch index's filterable-attribute
        // configuration.
        $ownEmployeeId = $this->ownEmployeeIdIfScoped();
        if ($ownEmployeeId !== null) {
            $query->query(fn ($q) => $q->where('employee_id', $ownEmployeeId));
        }

        return $query
            ->take($limit)
            ->get()
            ->load('employee')
            ->map(function (LeaveRequest $leave) use ($ownEmployeeId): array {
                // Leave `reason` is free-form text (medical, personal, …)
                // and must never leak across employees. Only show it when
                // the row belongs to the caller themself; cross-employee
                // rows are still visible to privileged readers but with
                // the reason blanked out.
                $showReason = $ownEmployeeId !== null
                    ? (int) $leave->employee_id === $ownEmployeeId
                    : ((int) $leave->employee_id === (int) (Auth::user()?->employee_id ?? 0));

                return [
                    'type' => 'leave',
                    'id' => $leave->id,
                    'title' => $leave->employee?->full_name ?? 'طلب إجازة',
                    'subtitle' => $showReason ? $this->truncate((string) $leave->reason, 80) : '',
                    'url' => "/leaves/{$leave->id}",
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Own-scope helper: when the caller has neither `view-reports` nor
     * `manage-workflows`, requests + leaves search must be pinned to
     * their own `employee_id`. Returns the id to scope by, or null when
     * the caller is privileged (no scoping — see everything).
     */
    private function ownEmployeeIdIfScoped(): ?int
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            // Unauthenticated shouldn't reach the search endpoint, but
            // if it ever does, return an id that matches nothing so the
            // fan-out returns an empty set.
            return 0;
        }

        if ($user->can('view-reports') || $user->can('manage-workflows')) {
            return null;
        }

        // No linked employee => scope to a sentinel that matches nothing.
        return (int) ($user->employee_id ?? 0);
    }

    private function truncate(string $value, int $length): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length - 1).'…';
    }
}
