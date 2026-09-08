<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Requests\AnalyticsFiltersRequest;
use App\Shared\Enums\EmployeeStatus;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;

/**
 * Employee-side analytics — headcount snapshot, org breakdowns, hire
 * trend. Only lightly filtered by the request: department/team filters
 * narrow the whole snapshot; the from/to range only limits "recent hires".
 * A dashboard consumer looking at "who works here right now" wants the
 * current cohort regardless of the reporting window.
 */
class EmployeeAnalyticsService
{
    /**
     * @return array{
     *   headcount: array{total: int, active: int, inactive: int, on_leave: int, terminated: int},
     *   by_department: array<int, array{name: string, count: int}>,
     *   by_team: array<int, array{name: string, count: int}>,
     *   recent_hires: array<int, array{id: int, name: string, employee_number: int, hire_date: ?string, department: ?string}>,
     * }
     */
    public function summary(AnalyticsFiltersRequest $filters): array
    {
        return [
            'headcount' => $this->headcount($filters),
            'by_department' => $this->byDepartment($filters),
            'by_team' => $this->byTeam($filters),
            'recent_hires' => $this->recentHires($filters),
        ];
    }

    /**
     * @return array{total: int, active: int, inactive: int, on_leave: int, terminated: int}
     */
    private function headcount(AnalyticsFiltersRequest $filters): array
    {
        $rows = $this->applyFilters(DB::table('employees'), $filters)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'total' => (int) $rows->sum(),
            'active' => (int) ($rows[EmployeeStatus::Active->value] ?? 0),
            'inactive' => (int) ($rows[EmployeeStatus::Inactive->value] ?? 0),
            'on_leave' => (int) ($rows[EmployeeStatus::OnLeave->value] ?? 0),
            'terminated' => (int) ($rows[EmployeeStatus::Terminated->value] ?? 0),
        ];
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private function byDepartment(AnalyticsFiltersRequest $filters): array
    {
        return $this->applyFilters(DB::table('employees as e'), $filters, 'e')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->where('e.status', EmployeeStatus::Active->value)
            ->selectRaw('COALESCE(d.name, "بدون قسم") as name, COUNT(*) as c')
            ->groupBy('d.id', 'd.name')
            ->orderByDesc('c')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'count' => (int) $row->c,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private function byTeam(AnalyticsFiltersRequest $filters): array
    {
        return $this->applyFilters(DB::table('employees as e'), $filters, 'e')
            ->leftJoin('teams as t', 't.id', '=', 'e.team_id')
            ->where('e.status', EmployeeStatus::Active->value)
            ->whereNotNull('e.team_id')
            ->selectRaw('t.name as name, COUNT(*) as c')
            ->groupBy('t.id', 't.name')
            ->orderByDesc('c')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'count' => (int) $row->c,
            ])
            ->all();
    }

    /**
     * The one place where from/to matters — the "who joined recently"
     * list is scoped to the same reporting window every other card uses,
     * so a filter change consistently refreshes the whole page.
     *
     * @return array<int, array{id: int, name: string, employee_number: int, hire_date: ?string, department: ?string}>
     */
    private function recentHires(AnalyticsFiltersRequest $filters): array
    {
        return $this->applyFilters(DB::table('employees as e'), $filters, 'e')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->whereBetween('e.hire_date', [$filters->from()->toDateString(), $filters->to()->toDateString()])
            ->selectRaw('e.id, e.full_name as name, e.employee_number, e.hire_date, d.name as department')
            ->orderByDesc('e.hire_date')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'employee_number' => (int) $row->employee_number,
                'hire_date' => $row->hire_date ? (string) $row->hire_date : null,
                'department' => $row->department ? (string) $row->department : null,
            ])
            ->all();
    }

    /**
     * dept/team/employee filters applied to any employees query. Table
     * alias is passed in when the caller needs to disambiguate (some
     * queries alias `employees` as `e`, some don't).
     */
    private function applyFilters($query, AnalyticsFiltersRequest $filters, string $alias = '')
    {
        $prefix = $alias === '' ? '' : "$alias.";

        if ($filters->employeeId() !== null) {
            $query->where("{$prefix}id", $filters->employeeId());
        }
        if ($filters->departmentId() !== null) {
            $query->where("{$prefix}department_id", $filters->departmentId());
        }
        if ($filters->teamId() !== null) {
            $query->where("{$prefix}team_id", $filters->teamId());
        }

        return $query;
    }
}
