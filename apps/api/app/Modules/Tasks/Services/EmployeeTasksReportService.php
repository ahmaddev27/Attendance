<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\Employee;
use App\Models\Task;
use App\Modules\Tasks\Repositories\TaskRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Per-employee task report covering both the tasks assigned to the employee
 * and the tasks they created. The window is bucketed on created_at in
 * Asia/Gaza (never config('app.timezone')) so a task created at 23:30 Gaza
 * time lands in the month an admin sees on their calendar.
 */
class EmployeeTasksReportService
{
    private const TIMEZONE = 'Asia/Gaza';

    private const MONTH_LABELS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
        7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    public function __construct(private readonly TaskRepository $tasks) {}

    /**
     * @return array<string, mixed>
     */
    public function report(Employee $employee, int $year, ?int $month = null): array
    {
        [$from, $to] = $this->window($year, $month);
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();

        $rows = $this->tasks
            ->involvingEmployeeBetween($employee->id, $from, $to)
            ->map(fn (Task $task): array => $this->row($task, $employee, $today));

        return [
            'employee' => $this->employeePayload($employee),
            'year' => $year,
            'month' => $month,
            'totals' => $this->totals($rows),
            'by_month' => $this->byMonth($rows, $year),
            'by_status' => $this->byStatus($rows),
            'by_priority' => $this->byPriority($rows),
            'tasks' => $rows->map(fn (array $r): array => $this->publicRow($r))->values()->all(),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(int $year, ?int $month): array
    {
        $start = CarbonImmutable::create($year, $month ?? 1, 1, 0, 0, 0, self::TIMEZONE);
        $end = $month === null ? $start->endOfYear() : $start->endOfMonth();

        return [$start->utc(), $end->utc()];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'department' => $employee->department?->name,
        ];
    }

    /**
     * Internal row: public fields plus the classification keys the
     * aggregations need (stripped again by publicRow()).
     *
     * @return array<string, mixed>
     */
    private function row(Task $task, Employee $employee, string $today): array
    {
        $status = $task->status;
        $done = (bool) $status?->is_done_state;
        $cancelled = (bool) $status?->is_cancelled_state;
        $createdAt = $task->created_at?->setTimezone(self::TIMEZONE);
        $completedAt = $task->completed_at?->setTimezone(self::TIMEZONE);

        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $status?->name,
            'priority' => $task->priority?->name,
            'assigned_role' => $task->assigned_to === $employee->id ? 'assignee' : 'creator',
            'created_at' => $createdAt?->toIso8601String(),
            'due_date' => $task->due_date?->toDateString(),
            'completed_at' => $completedAt?->toIso8601String(),
            'is_overdue' => ! $done && ! $cancelled && $task->completed_at === null
                && $task->due_date !== null && $task->due_date->toDateString() < $today,
            '_bucket' => $this->bucket($done, $cancelled, (int) $task->progress_percent),
            '_created_month' => $createdAt?->month,
            '_completed_month' => $completedAt?->month,
            '_completed_year' => $completedAt?->year,
            '_status_id' => $task->status_id,
            '_status_color' => $status?->color,
            '_priority_id' => $task->priority_id,
        ];
    }

    private function bucket(bool $done, bool $cancelled, int $progress): string
    {
        return match (true) {
            $done => 'completed',
            $cancelled => 'cancelled',
            $progress > 0 => 'in_progress',
            default => 'pending',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function publicRow(array $row): array
    {
        return array_filter($row, fn (string $key): bool => ! str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function totals(Collection $rows): array
    {
        $count = fn (string $bucket): int => $rows->where('_bucket', $bucket)->count();

        return [
            'total' => $rows->count(),
            'pending' => $count('pending'),
            'in_progress' => $count('in_progress'),
            'completed' => $count('completed'),
            'cancelled' => $count('cancelled'),
            'overdue' => $rows->where('is_overdue', true)->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byMonth(Collection $rows, int $year): array
    {
        return collect(self::MONTH_LABELS)->map(fn (string $label, int $m): array => [
            'month' => $m,
            'label' => $label,
            'assigned' => $rows->where('_created_month', $m)->count(),
            'completed' => $rows->where('_completed_year', $year)->where('_completed_month', $m)->count(),
            'overdue' => $rows->where('_created_month', $m)->where('is_overdue', true)->count(),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byStatus(Collection $rows): array
    {
        return $rows->groupBy('_status_id')->map(fn (Collection $group, $id): array => [
            'status_id' => (int) $id,
            'name' => $group->first()['status'],
            'count' => $group->count(),
            'color' => $group->first()['_status_color'],
        ])->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byPriority(Collection $rows): array
    {
        return $rows->groupBy('_priority_id')->map(fn (Collection $group, $id): array => [
            'priority_id' => (int) $id,
            'name' => $group->first()['priority'],
            'count' => $group->count(),
        ])->values()->all();
    }
}
