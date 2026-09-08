<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\LeaveStatus;
use App\Shared\Enums\RequestStatus;
use Carbon\CarbonImmutable;

/**
 * Aggregates the org-wide KPIs shown on the admin dashboard.
 *
 * Everything is a plain count / groupBy — no joins across modules — so the
 * service stays cheap enough to run on every dashboard page load without a
 * cache layer. If the numbers become too heavy later, wrap the return
 * value in Cache::remember(...) at the controller boundary.
 */
class AdminDashboardService
{
    /**
     * @return array{
     *   employees: array{total: int, active: int, inactive: int},
     *   today: array{
     *     date: string,
     *     present: int,
     *     late: int,
     *     absent: int,
     *     on_leave: int,
     *   },
     *   pending: array{leaves: int, requests: int, total: int},
     *   tasks: array{open: int, in_progress: int, overdue: int},
     * }
     */
    public function kpis(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now()->startOfDay();
        $todayDate = $today->toDateString();

        return [
            'employees' => $this->employeeCounts(),
            'today' => $this->todayAttendance($todayDate),
            'pending' => $this->pendingApprovals(),
            'tasks' => $this->taskCounts($today),
        ];
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    private function employeeCounts(): array
    {
        // Single grouped query — one row per EmployeeStatus value (active,
        // inactive, on_leave, terminated). We collapse everything that
        // isn't 'active' into the inactive bucket for the KPI card, since
        // the sidebar's own filter treats on_leave/terminated as
        // "temporarily not around" rather than a separate cohort.
        $rows = Employee::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $active = (int) ($rows->get(EmployeeStatus::Active->value) ?? 0);
        $total = (int) $rows->sum();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
        ];
    }

    /**
     * @return array{date: string, present: int, late: int, absent: int, on_leave: int}
     */
    private function todayAttendance(string $todayDate): array
    {
        $counts = Attendance::query()
            ->where('date', $todayDate)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'date' => $todayDate,
            'present' => (int) ($counts[AttendanceStatus::Present->value] ?? 0)
                + (int) ($counts[AttendanceStatus::Remote->value] ?? 0)
                + (int) ($counts[AttendanceStatus::BusinessMission->value] ?? 0),
            'late' => (int) ($counts[AttendanceStatus::Late->value] ?? 0)
                + (int) ($counts[AttendanceStatus::EarlyLeave->value] ?? 0),
            'absent' => (int) ($counts[AttendanceStatus::Absent->value] ?? 0),
            'on_leave' => (int) ($counts[AttendanceStatus::OnLeave->value] ?? 0),
        ];
    }

    /**
     * @return array{leaves: int, requests: int, total: int}
     */
    private function pendingApprovals(): array
    {
        $leaves = LeaveRequest::query()
            ->where('status', LeaveStatus::Pending->value)
            ->count();

        $requests = RequestModel::query()
            ->where('status', RequestStatus::Pending->value)
            ->count();

        return [
            'leaves' => $leaves,
            'requests' => $requests,
            'total' => $leaves + $requests,
        ];
    }

    /**
     * @return array{open: int, in_progress: int, overdue: int}
     */
    private function taskCounts(CarbonImmutable $today): array
    {
        // Uses the TaskStatus join to exclude done/cancelled without
        // hard-coding those code strings — an admin-configurable status
        // could still be treated as "closed" via its boolean flags.
        $baseOpen = Task::query()
            ->whereHas('status', function ($q): void {
                $q->where('is_done_state', false)->where('is_cancelled_state', false);
            });

        $open = (clone $baseOpen)->count();

        // "In progress" = started (start_date is on or before today) and
        // not yet completed. Progress > 0 also counts, since a task can be
        // worked on before its scheduled start.
        $inProgress = (clone $baseOpen)
            ->whereNull('completed_at')
            ->where(function ($q) use ($today): void {
                $q->whereDate('start_date', '<=', $today->toDateString())
                    ->orWhere('progress_percent', '>', 0);
            })
            ->count();

        $overdue = (clone $baseOpen)
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->count();

        return [
            'open' => $open,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
        ];
    }
}
