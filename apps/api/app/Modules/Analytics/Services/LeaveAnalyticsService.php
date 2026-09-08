<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Requests\AnalyticsFiltersRequest;
use Illuminate\Support\Facades\DB;

/**
 * Leave-side analytics — where days-off cluster and what people take them
 * for. Only APPROVED leave counts (draft/pending/rejected/cancelled are
 * noise; the value of the report is "what actually happened").
 *
 * Three cuts of the same underlying set, each a single grouped query:
 *   • per-month total-days trend (matches the frontend line chart)
 *   • per-type breakdown (annual / sick / unpaid / …)
 *   • per-department breakdown (which teams take the most time off)
 */
class LeaveAnalyticsService
{
    /**
     * @return array{
     *   by_month: array<int, array{month: string, days: float, requests: int}>,
     *   by_type: array<int, array{name: string, days: float, requests: int}>,
     *   by_department: array<int, array{name: string, days: float, requests: int}>,
     *   totals: array{days: float, requests: int},
     * }
     */
    public function patterns(AnalyticsFiltersRequest $filters): array
    {
        $from = $filters->from()->toDateString();
        $to = $filters->to()->toDateString();

        // Base filter set — reused three times. We deliberately match on
        // `start_date` for the range even for multi-day leaves; using
        // `overlaps` would inflate the totals across month boundaries.
        $base = fn () => DB::table('leave_requests as lr')
            ->where('lr.status', 'approved')
            ->whereBetween('lr.start_date', [$from, $to]);

        $byMonth = $this->groupByMonth($this->attach($base(), $filters));
        $byType = $this->groupByType($this->attach($base(), $filters));
        $byDept = $this->groupByDepartment($this->attach($base(), $filters));

        // Totals from the flat query — cheaper than summing PHP arrays and
        // gives a real ground-truth denominator for the frontend.
        $totalsRow = $this->attach($base(), $filters)
            ->selectRaw('COALESCE(SUM(lr.days), 0) as total_days, COUNT(*) as total_reqs')
            ->first();

        return [
            'by_month' => $byMonth,
            'by_type' => $byType,
            'by_department' => $byDept,
            'totals' => [
                'days' => (float) ($totalsRow->total_days ?? 0),
                'requests' => (int) ($totalsRow->total_reqs ?? 0),
            ],
        ];
    }

    /**
     * @return array<int, array{month: string, days: float, requests: int}>
     */
    private function groupByMonth($query): array
    {
        return $query
            ->selectRaw('DATE_FORMAT(lr.start_date, "%Y-%m") as month, SUM(lr.days) as days, COUNT(*) as requests')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => (string) $row->month,
                'days' => (float) $row->days,
                'requests' => (int) $row->requests,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, days: float, requests: int}>
     */
    private function groupByType($query): array
    {
        return $query
            ->join('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
            ->selectRaw('lt.name_ar as name, SUM(lr.days) as days, COUNT(*) as requests')
            ->groupBy('lt.id', 'lt.name_ar')
            ->orderByDesc('days')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'days' => (float) $row->days,
                'requests' => (int) $row->requests,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, days: float, requests: int}>
     */
    private function groupByDepartment($query): array
    {
        return $query
            ->join('employees as e', 'e.id', '=', 'lr.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->selectRaw('COALESCE(d.name, "بدون قسم") as name, SUM(lr.days) as days, COUNT(*) as requests')
            ->groupBy('d.id', 'd.name')
            ->orderByDesc('days')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'days' => (float) $row->days,
                'requests' => (int) $row->requests,
            ])
            ->all();
    }

    /**
     * Attach the employee-side filters to a fresh query builder. Same
     * short-circuit logic as AttendanceAnalyticsService — we only pay the
     * `employees` join when a dept/team/employee filter is set.
     */
    private function attach($query, AnalyticsFiltersRequest $filters)
    {
        if ($filters->employeeId() !== null) {
            return $query->where('lr.employee_id', $filters->employeeId());
        }

        $dept = $filters->departmentId();
        $team = $filters->teamId();

        if ($dept !== null || $team !== null) {
            $query->join('employees as e_f', 'e_f.id', '=', 'lr.employee_id');
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
