<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Requests\AnalyticsFiltersRequest;
use Illuminate\Support\Facades\DB;

/**
 * Attendance-side analytics. Two shapes:
 *
 *   kpis(...)     → headline numbers (present / late / absent / on_leave,
 *                   attendance-rate %, avg check-in + check-out clock times).
 *   heatmap(...)  → 7×24 matrix of check-in counts (day-of-week × hour) for
 *                   the dashboard's "when do people actually show up" plot.
 *
 * Both are single SQL round-trips against the `attendances` table with
 * conditional joins to `employees` when a dept/team/employee filter is
 * present — no ORM hydration, since we only need aggregates.
 *
 * Filters are applied at the SQL boundary rather than in a scope chain so
 * one query does the entire slice, and Analytics-wide cache keys (see
 * AnalyticsService) stay identical whether the caller filters or not.
 */
class AttendanceAnalyticsService
{
    /**
     * @return array{
     *   totals: array{present: int, late: int, absent: int, on_leave: int, other: int},
     *   attendance_rate: float,
     *   avg_check_in: ?string,
     *   avg_check_out: ?string,
     *   working_days: int,
     * }
     */
    public function kpis(AnalyticsFiltersRequest $filters): array
    {
        $query = DB::table('attendances as a')
            ->whereBetween('a.date', [$filters->from()->toDateString(), $filters->to()->toDateString()]);

        $this->applyEmployeeFilters($query, $filters);

        // One grouped scan: buckets per status plus the two clock aggregates.
        // AVG on TIME columns in MySQL 8 returns seconds-since-midnight; we
        // convert to HH:MM in PHP land so the frontend renders it as-is.
        // SUM + COUNT(is-not-null) instead of AVG × row-count. AVG in
        // MySQL ignores NULLs correctly, but weighting the per-status
        // average by `COUNT(*)` (which INCLUDES rows where check_in_at
        // is null, e.g. absent/on_leave) inflates the denominator and
        // pulls the org-wide "avg check-in" toward whichever bucket
        // has the most nulls — a systematic bias, not a one-off.
        $rows = (clone $query)
            ->selectRaw('
                a.status,
                COUNT(*) as c,
                SUM(TIME_TO_SEC(TIME(a.check_in_at))) as sum_in_sec,
                SUM(a.check_in_at IS NOT NULL) as n_in,
                SUM(TIME_TO_SEC(TIME(a.check_out_at))) as sum_out_sec,
                SUM(a.check_out_at IS NOT NULL) as n_out
            ')
            ->groupBy('a.status')
            ->get();

        $totals = ['present' => 0, 'late' => 0, 'absent' => 0, 'on_leave' => 0, 'other' => 0];
        $checkInSecTotal = 0.0;
        $checkInWeight = 0;
        $checkOutSecTotal = 0.0;
        $checkOutWeight = 0;

        foreach ($rows as $row) {
            // Bucket every status into one of the five KPI slots so the
            // frontend gets a stable shape whatever new attendance codes
            // land later. "Present" folds in Remote + BusinessMission
            // because they're all "showed up somewhere for work".
            $bucket = match ($row->status) {
                'present', 'remote', 'business_mission' => 'present',
                'late', 'early_leave' => 'late',
                'absent' => 'absent',
                'on_leave' => 'on_leave',
                default => 'other',
            };
            $totals[$bucket] += (int) $row->c;

            $checkInSecTotal += (float) ($row->sum_in_sec ?? 0);
            $checkInWeight += (int) ($row->n_in ?? 0);
            $checkOutSecTotal += (float) ($row->sum_out_sec ?? 0);
            $checkOutWeight += (int) ($row->n_out ?? 0);
        }

        $totalRecords = array_sum($totals);
        // Attendance rate = "showed up" ÷ "should have shown up (not
        // weekend / holiday / approved leave)". Excluding on_leave was
        // missing — employees on approved leave were counted as missed
        // workdays, so the org rate was systematically depressed by the
        // vacation calendar.
        $eligible = $totalRecords - $totals['other'] - $totals['on_leave'];
        $rate = $eligible > 0
            ? round(($totals['present'] + $totals['late']) / $eligible * 100, 1)
            : 0.0;

        return [
            'totals' => $totals,
            'attendance_rate' => $rate,
            'avg_check_in' => $checkInWeight > 0
                ? $this->secondsToClock((int) round($checkInSecTotal / $checkInWeight))
                : null,
            'avg_check_out' => $checkOutWeight > 0
                ? $this->secondsToClock((int) round($checkOutSecTotal / $checkOutWeight))
                : null,
            'working_days' => $eligible,
        ];
    }

    /**
     * 7×24 count matrix. Rows are indexed 0-6 (Sunday..Saturday to match
     * MySQL DAYOFWEEK - 1) so the frontend can render `days[dayIndex]`
     * directly. Zero-filled: every cell is an int, so the heatmap
     * component never has to reason about missing keys.
     *
     * @return array{
     *   matrix: array<int, array<int, int>>,
     *   max: int,
     * }
     */
    public function heatmap(AnalyticsFiltersRequest $filters): array
    {
        $query = DB::table('attendances as a')
            ->whereBetween('a.date', [$filters->from()->toDateString(), $filters->to()->toDateString()])
            ->whereNotNull('a.check_in_at');

        $this->applyEmployeeFilters($query, $filters);

        $rows = $query
            ->selectRaw('
                (DAYOFWEEK(a.check_in_at) - 1) as dow,
                HOUR(a.check_in_at) as h,
                COUNT(*) as c
            ')
            ->groupBy('dow', 'h')
            ->get();

        // Pre-fill with zeros so the frontend gets a rectangular matrix.
        $matrix = array_fill(0, 7, array_fill(0, 24, 0));
        $max = 0;

        foreach ($rows as $row) {
            $dow = (int) $row->dow;
            $hour = (int) $row->h;
            $count = (int) $row->c;
            $matrix[$dow][$hour] = $count;
            if ($count > $max) {
                $max = $count;
            }
        }

        return ['matrix' => $matrix, 'max' => $max];
    }

    /**
     * Add whichever employee-side filter narrows the query. Each filter
     * short-circuits before the join, so a "no filters" scan stays on
     * `attendances` alone (index-friendly) and never pays the join cost.
     */
    private function applyEmployeeFilters($query, AnalyticsFiltersRequest $filters): void
    {
        if ($filters->employeeId() !== null) {
            $query->where('a.employee_id', $filters->employeeId());
            return;
        }

        $dept = $filters->departmentId();
        $team = $filters->teamId();

        if ($dept !== null || $team !== null) {
            $query->join('employees as e', 'e.id', '=', 'a.employee_id');
            if ($dept !== null) {
                $query->where('e.department_id', $dept);
            }
            if ($team !== null) {
                $query->where('e.team_id', $team);
            }
        }
    }

    private function secondsToClock(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $mins = intdiv($seconds % 3600, 60);
        return sprintf('%02d:%02d', $hours, $mins);
    }
}
