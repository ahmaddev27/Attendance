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
        // A task is "open" when:
        //   • its status is not a done/cancelled state, AND
        //   • completed_at is null.
        //
        // Both guards are needed: an admin can move a task to a done-state
        // status without necessarily going through the "complete" action,
        // and the "complete" action can stamp completed_at without moving
        // the status (rare, but legal). Counting either alone would leak
        // finished tasks into the "open" number.
        $baseOpen = Task::query()
            ->whereNull('completed_at')
            ->whereHas('status', function ($q): void {
                $q->where('is_done_state', false)->where('is_cancelled_state', false);
            });

        $open = (clone $baseOpen)->count();

        // "In progress" = actively being worked on — progress > 0. A task
        // that was just created with progress 0 is technically "open" but
        // not yet "in progress"; the previous rule counted any task whose
        // start_date had arrived, which surprised users who filed a task
        // "for tomorrow's execution" and saw it counted as active today.
        $inProgress = (clone $baseOpen)
            ->where('progress_percent', '>', 0)
            ->count();

        $overdue = (clone $baseOpen)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today->toDateString())
            ->count();

        return [
            'open' => $open,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
        ];
    }
}
