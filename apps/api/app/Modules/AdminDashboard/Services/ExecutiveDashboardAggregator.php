<?php

declare(strict_types=1);

namespace App\Modules\AdminDashboard\Services;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\LeaveStatus;
use App\Shared\Enums\RequestStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only aggregator behind the executive dashboard (M8). Every public
 * method is wrapped in a short-lived cache so a busy dashboard screen
 * (typically polled every few seconds by a frontend) doesn't recompute the
 * same counts against the database on every request — 5 minutes is a
 * deliberate trade-off between freshness and load, matching the SRS's
 * "near real-time" wording rather than "real-time".
 */
class ExecutiveDashboardAggregator
{
    private const CACHE_TTL_MINUTES = 5;

    /**
     * @return array<string, mixed>
     */
    public function summary(?int $companyId = null): array
    {
        return Cache::remember(
            $this->cacheKey('summary', $companyId),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($companyId) {
                $employeeIds = $this->companyEmployeeIds($companyId);
                $today = Carbon::today();

                return [
                    'employees' => $this->employeeCounts($employeeIds),
                    'attendance_today' => $this->attendanceToday($employeeIds, $today),
                    'requests' => $this->requestCounts($employeeIds),
                    'leaves' => $this->leaveCounts($employeeIds),
                    'tasks' => $this->taskCounts($employeeIds),
                    'departments' => $this->topDepartments($companyId),
                    'attendance_trend_7d' => $this->attendanceTrend(7, $companyId),
                ];
            }
        );
    }

    /**
     * @return array<int, array{date: string, present: int}>
     */
    public function attendanceTrend(int $days = 7, ?int $companyId = null): array
    {
        $days = max(1, $days);

        return Cache::remember(
            $this->cacheKey("attendance-trend:{$days}", $companyId),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($days, $companyId) {
                $employeeIds = $this->companyEmployeeIds($companyId);
                $end = Carbon::today();
                $start = $end->copy()->subDays($days - 1);

                // Raw select on the `date` column (not the Eloquent-cast
                // attribute) so GROUP BY sees the exact stored 'Y-m-d'
                // string on both MySQL and SQLite, avoiding per-row PHP
                // grouping over a potentially large attendance table.
                $counts = Attendance::query()
                    ->whereNotNull('check_in_at')
                    ->whereDate('date', '>=', $start->toDateString())
                    ->whereDate('date', '<=', $end->toDateString())
                    ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
                    ->selectRaw('date, COUNT(*) as present_count')
                    ->groupBy('date')
                    ->pluck('present_count', 'date');

                $trend = [];

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $key = $date->toDateString();
                    $trend[] = ['date' => $key, 'present' => (int) ($counts[$key] ?? 0)];
                }

                return $trend;
            }
        );
    }

    /**
     * @return array<int, array{id: int, name: string, employees_count: int, avg_attendance_percentage: float, tasks_completion_rate: float}>
     */
    public function departmentPerformance(?int $departmentId = null): array
    {
        return Cache::remember(
            $this->cacheKey('department-performance', $departmentId),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($departmentId) {
                $startOfMonth = Carbon::today()->startOfMonth();
                $today = Carbon::today();
                $elapsedDays = $startOfMonth->diffInDays($today) + 1;

                return Department::query()
                    ->when($departmentId, fn ($query, $id) => $query->where('id', $id))
                    ->withCount('employees')
                    ->get()
                    ->map(fn (Department $department) => $this->departmentPerformanceRow($department, $startOfMonth, $today, $elapsedDays))
                    ->all();
            }
        );
    }

    /**
     * @return array{total: int, active: int, on_leave: int, joined_this_month: int}
     */
    private function employeeCounts(?Collection $employeeIds): array
    {
        $now = Carbon::now();

        return [
            'total' => $this->employeeQuery($employeeIds)->count(),
            'active' => $this->employeeQuery($employeeIds)->where('status', EmployeeStatus::Active)->count(),
            'on_leave' => $this->employeeQuery($employeeIds)->where('status', EmployeeStatus::OnLeave)->count(),
            'joined_this_month' => $this->employeeQuery($employeeIds)
                ->whereYear('joining_date', $now->year)
                ->whereMonth('joining_date', $now->month)
                ->count(),
        ];
    }

    /**
     * @return array{present: int, absent_expected: int, late: int, on_leave_today: int}
     */
    private function attendanceToday(?Collection $employeeIds, Carbon $today): array
    {
        $present = Attendance::query()
            ->whereDate('date', $today)
            ->whereNotNull('check_in_at')
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->count();

        $late = Attendance::query()
            ->whereDate('date', $today)
            ->where('late_minutes', '>', 0)
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->count();

        $onLeaveToday = LeaveRequest::query()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
            ->count();

        $absentExpected = $this->countExpectedButAbsent($employeeIds, $today);

        return [
            'present' => $present,
            'absent_expected' => $absentExpected,
            'late' => $late,
            'on_leave_today' => $onLeaveToday,
        ];
    }

    /**
     * Active employees who are expected in today (per their work
     * schedule's workdays, or always-expected when they have no schedule
     * assigned yet) but have neither an attendance row nor an approved
     * leave covering today. Filtering happens in PHP over an already
     * eager-loaded, id/schedule-only projection rather than per-employee
     * queries, so this stays a handful of queries regardless of headcount.
     */
    private function countExpectedButAbsent(?Collection $employeeIds, Carbon $today): int
    {
        $activeEmployees = $this->employeeQuery($employeeIds)
            ->where('status', EmployeeStatus::Active)
            ->with('workSchedule:id,workdays')
            ->get(['id', 'work_schedule_id']);

        $expectedIds = $activeEmployees
            ->filter(fn (Employee $employee) => $employee->workSchedule === null || $employee->workSchedule->isWorkday($today))
            ->pluck('id');

        if ($expectedIds->isEmpty()) {
            return 0;
        }

        $presentIds = Attendance::query()
            ->whereDate('date', $today)
            ->whereIn('employee_id', $expectedIds)
            ->pluck('employee_id');

        $onLeaveIds = LeaveRequest::query()
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereIn('employee_id', $expectedIds)
            ->pluck('employee_id');

        return $expectedIds->diff($presentIds)->diff($onLeaveIds)->count();
    }

    /**
     * @return array{pending_total: int, submitted_this_week: int}
     */
    private function requestCounts(?Collection $employeeIds): array
    {
        return [
            'pending_total' => RequestModel::query()
                ->where('status', RequestStatus::Pending)
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->count(),
            'submitted_this_week' => RequestModel::query()
                ->where('status', '!=', RequestStatus::Draft)
                ->where('created_at', '>=', Carbon::now()->startOfWeek())
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->count(),
        ];
    }

    /**
     * @return array{pending: int, approved_this_month: int}
     */
    private function leaveCounts(?Collection $employeeIds): array
    {
        $now = Carbon::now();

        return [
            'pending' => LeaveRequest::query()
                ->pending()
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->count(),
            // Scoped by year as well as month (not just month, as a literal
            // "this month" reading would wrongly also match last year's
            // same month) — reviewed_at is when the decision was made.
            'approved_this_month' => LeaveRequest::query()
                ->approved()
                ->whereYear('reviewed_at', $now->year)
                ->whereMonth('reviewed_at', $now->month)
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->count(),
        ];
    }

    /**
     * @return array{total_open: int, overdue: int, completed_this_week: int}
     */
    private function taskCounts(?Collection $employeeIds): array
    {
        return [
            'total_open' => Task::query()
                ->whereHas('status', fn ($query) => $query->where('is_done_state', false)->where('is_cancelled_state', false))
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('assigned_to', $employeeIds))
                ->count(),
            'overdue' => Task::query()
                ->whereHas('status', fn ($query) => $query->where('is_done_state', false))
                ->whereNotNull('due_date')
                ->where('due_date', '<', Carbon::today())
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('assigned_to', $employeeIds))
                ->count(),
            'completed_this_week' => Task::query()
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', Carbon::now()->startOfWeek())
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('assigned_to', $employeeIds))
                ->count(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, employees_count: int}>
     */
    private function topDepartments(?int $companyId): array
    {
        return Department::query()
            ->when($companyId, fn ($query, $id) => $query->where('company_id', $id))
            ->withCount('employees')
            ->orderByDesc('employees_count')
            ->limit(5)
            ->get()
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'employees_count' => $department->employees_count,
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, employees_count: int, avg_attendance_percentage: float, tasks_completion_rate: float}
     */
    private function departmentPerformanceRow(Department $department, Carbon $startOfMonth, Carbon $today, int $elapsedDays): array
    {
        $employeeIds = Employee::query()->where('department_id', $department->id)->pluck('id');

        $presentCount = $employeeIds->isEmpty() ? 0 : Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $startOfMonth)
            ->whereDate('date', '<=', $today)
            ->whereNotNull('check_in_at')
            ->count();

        $avgAttendancePercentage = $employeeIds->isNotEmpty() && $elapsedDays > 0
            ? round(($presentCount / ($employeeIds->count() * $elapsedDays)) * 100, 2)
            : 0.0;

        $totalTasks = $employeeIds->isEmpty() ? 0 : Task::query()->whereIn('assigned_to', $employeeIds)->count();
        $completedTasks = $totalTasks === 0 ? 0 : Task::query()
            ->whereIn('assigned_to', $employeeIds)
            ->whereHas('status', fn ($query) => $query->where('is_done_state', true))
            ->count();

        return [
            'id' => $department->id,
            'name' => $department->name,
            'employees_count' => $department->employees_count,
            'avg_attendance_percentage' => $avgAttendancePercentage,
            'tasks_completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0.0,
        ];
    }

    private function employeeQuery(?Collection $employeeIds): \Illuminate\Database\Eloquent\Builder
    {
        return Employee::query()->when($employeeIds !== null, fn ($query) => $query->whereIn('id', $employeeIds));
    }

    /**
     * Employee ids belonging to $companyId (via their department), or null
     * to mean "no restriction" — every counting query above treats null as
     * "don't filter" so the common (single-tenant) call pattern of never
     * passing a company id doesn't pay for an extra query or join.
     */
    private function companyEmployeeIds(?int $companyId): ?Collection
    {
        if ($companyId === null) {
            return null;
        }

        return Employee::query()
            ->whereHas('department', fn ($query) => $query->where('company_id', $companyId))
            ->pluck('id');
    }

    private function cacheKey(string $suffix, ?int $scopeId): string
    {
        return 'admin-dashboard:'.$suffix.':'.($scopeId ?? 'all');
    }
}
