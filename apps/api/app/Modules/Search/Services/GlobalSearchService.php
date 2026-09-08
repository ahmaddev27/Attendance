<?php

declare(strict_types=1);

namespace App\Modules\Search\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;

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
        return RequestModel::search($term)
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
        return LeaveRequest::search($term)
            ->take($limit)
            ->get()
            ->load('employee')
            ->map(fn (LeaveRequest $leave) => [
                'type' => 'leave',
                'id' => $leave->id,
                'title' => $leave->employee?->full_name ?? 'طلب إجازة',
                'subtitle' => $this->truncate((string) $leave->reason, 80),
                'url' => "/leaves/{$leave->id}",
            ])
            ->values()
            ->all();
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
