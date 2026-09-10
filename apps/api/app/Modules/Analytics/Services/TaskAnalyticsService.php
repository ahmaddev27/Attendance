<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Requests\AnalyticsFiltersRequest;
use Illuminate\Support\Facades\DB;

/**
 * Task-side analytics — completion pace, bucket totals, overdue count,
 * and top performers. Filters by `created_at` (which is when the task
 * entered the system) rather than `due_date` so long-lived tasks still
 * show up in the reporting window they were opened in.
 */
class TaskAnalyticsService
{
    /**
     * @return array{
     *   totals: array{total: int, open: int, in_progress: int, done: int, cancelled: int, overdue: int},
     *   avg_completion_hours: ?float,
     *   by_status: array<int, array{name: string, count: int}>,
     *   top_assignees: array<int, array{name: string, done: int, avg_hours: ?float}>,
     * }
     */
    public function performance(AnalyticsFiltersRequest $filters): array
    {
        $from = $filters->from()->startOfDay();
        $to = $filters->to()->endOfDay();

        $base = fn () => DB::table('tasks as t')
            ->whereBetween('t.created_at', [$from, $to]);

        return [
            'totals' => $this->totals($this->attach($base(), $filters), $to),
            'avg_completion_hours' => $this->avgCompletionHours($this->attach($base(), $filters)),
            'by_status' => $this->byStatus($this->attach($base(), $filters)),
            'top_assignees' => $this->topAssignees($this->attach($base(), $filters)),
        ];
    }

    /**
     * @return array{total: int, open: int, in_progress: int, done: int, cancelled: int, overdue: int}
     */
    private function totals($query, \Carbon\CarbonImmutable $to): array
    {
        // One-shot aggregate query — cheaper than four separate counts
        // because MySQL can walk `tasks` once and evaluate every CASE.
        // We join task_statuses so we can classify against the boolean
        // flags rather than status names (customers rename statuses).
        $row = (clone $query)
            ->join('task_statuses as ts', 'ts.id', '=', 't.status_id')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN ts.is_done_state = 1 THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN ts.is_cancelled_state = 1 THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN ts.is_done_state = 0 AND ts.is_cancelled_state = 0 AND t.progress_percent = 0 THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN ts.is_done_state = 0 AND ts.is_cancelled_state = 0 AND t.progress_percent > 0 THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN ts.is_done_state = 0 AND ts.is_cancelled_state = 0 AND t.due_date < ? THEN 1 ELSE 0 END) as overdue
            ', [$to->toDateString()])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'open' => (int) ($row->open ?? 0),
            'in_progress' => (int) ($row->in_progress ?? 0),
            'done' => (int) ($row->done ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
            'overdue' => (int) ($row->overdue ?? 0),
        ];
    }

    private function avgCompletionHours($query): ?float
    {
        // Only completed tasks contribute to the average — an open task's
        // "how long has it been open" is a different metric (see totals.open).
        $seconds = (clone $query)
            ->whereNotNull('t.completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, t.created_at, t.completed_at)) as avg_sec')
            ->value('avg_sec');

        return $seconds !== null ? round(((float) $seconds) / 3600, 1) : null;
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private function byStatus($query): array
    {
        return (clone $query)
            ->join('task_statuses as ts', 'ts.id', '=', 't.status_id')
            ->selectRaw('ts.name as name, COUNT(*) as c')
            ->groupBy('ts.id', 'ts.name', 'ts.sort_order')
            ->orderBy('ts.sort_order')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'count' => (int) $row->c,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, done: int, avg_hours: ?float}>
     */
    private function topAssignees($query): array
    {
        return (clone $query)
            ->join('task_statuses as ts', 'ts.id', '=', 't.status_id')
            ->join('employees as e', 'e.id', '=', 't.assigned_to')
            ->where('ts.is_done_state', true)
            ->whereNotNull('t.completed_at')
            ->selectRaw("
                CONCAT(e.first_name, ' ', e.last_name) as name,
                COUNT(*) as done,
                AVG(TIMESTAMPDIFF(SECOND, t.created_at, t.completed_at)) as avg_sec
            ")
            ->groupBy('e.id', 'e.first_name', 'e.last_name')
            ->orderByDesc('done')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'done' => (int) $row->done,
                'avg_hours' => $row->avg_sec !== null ? round(((float) $row->avg_sec) / 3600, 1) : null,
            ])
            ->all();
    }

    /**
     * Task filters go straight on `tasks.assigned_to` since we always join
     * `employees` anyway for the assignees table. dept/team narrow through
     * that same employee row rather than a second join.
     */
    private function attach($query, AnalyticsFiltersRequest $filters)
    {
        if ($filters->employeeId() !== null) {
            return $query->where('t.assigned_to', $filters->employeeId());
        }

        $dept = $filters->departmentId();
        $team = $filters->teamId();

        if ($dept !== null || $team !== null) {
            $query->join('employees as e_f', 'e_f.id', '=', 't.assigned_to');
            if ($dept !== null) {
                $query->where('e_f.department_id', $dept);
            }
            if ($team !== null) {
                $query->where('e_f.team_id', $team);
            }
        }

        return $query;
    }
}
