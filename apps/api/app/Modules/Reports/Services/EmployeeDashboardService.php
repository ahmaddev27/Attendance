<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use App\Models\User;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\LeaveStatus;
use App\Shared\Enums\RequestStatus;
use Carbon\CarbonImmutable;

/**
 * Personal KPIs shown on the employee /home page.
 *
 * Mirrors the admin-side AdminDashboardService but scoped to a single
 * user: their attendance this month, leave balances, open tasks, pending
 * request count, and today's status. Every query is filtered by
 * employee_id at the DB level so no cross-user data ever leaks even if
 * the caller forgets to check RBAC.
 */
class EmployeeDashboardService
{
    /**
     * @return array{
     *   today: array{status: string|null, checked_in_at: string|null, checked_out_at: string|null, open_session_since: string|null},
     *   month: array{present: int, late: int, absent: int, leave: int, working_days_elapsed: int},
     *   leaves: array{pending: int, upcoming: array{start_date: string, end_date: string, type: string}|null, balances: array<int, array{type: string, remaining: float, entitled: float}>},
     *   requests: array{pending: int},
     *   tasks: array{open: int, in_progress: int, overdue: int, completed_this_week: int},
     * }
     */
    public function forUser(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now()->startOfDay();
        $employee = $user->employee;

        // Users with no linked employee record (e.g., admins bootstrapped
        // before the org tree existed) get a zeroed payload — the frontend
        // renders it the same way, no branching needed.
        if (! $employee instanceof Employee) {
            return $this->empty($today);
        }

        return [
            'today' => $this->today($employee, $today),
            'month' => $this->month($employee, $today),
            'leaves' => $this->leaves($employee, $today),
            'requests' => $this->requests($employee),
            'tasks' => $this->tasks($employee, $today),
        ];
    }

    /**
     * @return array{status: string|null, checked_in_at: string|null, checked_out_at: string|null, open_session_since: string|null}
     */
    private function today(Employee $employee, CarbonImmutable $today): array
    {
        // Bare where() on the DATE column so the
        // UNIQUE(employee_id, date) index is used.
        $record = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today->toDateString())
            ->first();

        if (! $record) {
            return [
                'status' => null,
                'checked_in_at' => null,
                'checked_out_at' => null,
                'open_session_since' => null,
            ];
        }

        // Populated only while the session is still open — check_in_at
        // is set AND check_out_at is not. The frontend home banner keys
        // off this single field so it doesn't have to reason about the
        // status enum or reconcile checked_in_at with checked_out_at.
        $openSince = $record->check_in_at && ! $record->check_out_at
            ? $record->check_in_at->toIso8601String()
            : null;

        return [
            'status' => $record->status?->value,
            'checked_in_at' => $record->check_in_at?->toIso8601String(),
            'checked_out_at' => $record->check_out_at?->toIso8601String(),
            'open_session_since' => $openSince,
        ];
    }

    /**
     * @return array{present: int, late: int, absent: int, leave: int, working_days_elapsed: int}
     */
    private function month(Employee $employee, CarbonImmutable $today): array
    {
        $start = $today->startOfMonth();

        $counts = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $today->toDateString()])
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'present' => (int) ($counts[AttendanceStatus::Present->value] ?? 0)
                + (int) ($counts[AttendanceStatus::Remote->value] ?? 0)
                + (int) ($counts[AttendanceStatus::BusinessMission->value] ?? 0),
            'late' => (int) ($counts[AttendanceStatus::Late->value] ?? 0)
                + (int) ($counts[AttendanceStatus::EarlyLeave->value] ?? 0),
            'absent' => (int) ($counts[AttendanceStatus::Absent->value] ?? 0),
            'leave' => (int) ($counts[AttendanceStatus::OnLeave->value] ?? 0),
            'working_days_elapsed' => (int) $counts->sum(),
        ];
    }

    /**
     * @return array{pending: int, upcoming: array{start_date: string, end_date: string, type: string}|null, balances: array<int, array{type: string, remaining: float, entitled: float}>}
     */
    private function leaves(Employee $employee, CarbonImmutable $today): array
    {
        $pending = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Pending->value)
            ->count();

        $upcoming = LeaveRequest::query()
            ->with('leaveType:id,name')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved->value)
            // start_date is a DATE column — bare where() keeps the
            // leave_requests(start_date, end_date) index usable.
            ->where('start_date', '>=', $today->toDateString())
            ->orderBy('start_date')
            ->first();

        $balances = LeaveBalance::query()
            ->with('leaveType:id,name')
            ->where('employee_id', $employee->id)
            ->where('year', $today->year)
            ->get()
            ->map(fn (LeaveBalance $b) => [
                'type' => $b->leaveType?->name ?? '—',
                // Column is `entitlement`, not `entitled` — the old
                // code silently returned 0 for every employee because
                // `$b->entitled` was null → (float) null = 0.0. Use the
                // model's `remaining` accessor instead of re-deriving.
                'remaining' => (float) $b->remaining,
                'entitled' => (float) $b->entitlement,
            ])
            ->values()
            ->all();

        return [
            'pending' => $pending,
            'upcoming' => $upcoming ? [
                'start_date' => $upcoming->start_date->toDateString(),
                'end_date' => $upcoming->end_date->toDateString(),
                'type' => $upcoming->leaveType?->name ?? '—',
            ] : null,
            'balances' => $balances,
        ];
    }

    /**
     * @return array{pending: int}
     */
    private function requests(Employee $employee): array
    {
        return [
            'pending' => RequestModel::query()
                ->where('employee_id', $employee->id)
                ->where('status', RequestStatus::Pending->value)
                ->count(),
        ];
    }

    /**
     * @return array{open: int, in_progress: int, overdue: int, completed_this_week: int}
     */
    private function tasks(Employee $employee, CarbonImmutable $today): array
    {
        $todayDate = $today->toDateString();

        // Single-query classification: three counts share the same
        // scope (assigned to me, status is not done/cancelled, AND
        // completed_at IS NULL — both guards are needed because
        // "complete" can stamp completed_at without moving status, and
        // an admin can move status to a done-state without going
        // through the complete action; counting either alone leaks
        // finished tasks into "open").
        //
        // in_progress is stricter than before: progress_percent > 0.
        // A brand-new task with progress 0 whose start_date happens to
        // be today is "open", not "in progress" — the previous rule
        // surprised users who filed a task for today and saw it
        // counted as active work.
        $open = Task::query()
            ->from('tasks')
            ->join('task_statuses', 'task_statuses.id', '=', 'tasks.status_id')
            ->where('tasks.assigned_to', $employee->id)
            ->whereNull('tasks.completed_at')
            ->where('task_statuses.is_done_state', false)
            ->where('task_statuses.is_cancelled_state', false)
            ->selectRaw(
                'COUNT(*) as open_count,
                 SUM(CASE WHEN tasks.progress_percent > 0 THEN 1 ELSE 0 END) as in_progress_count,
                 SUM(CASE WHEN tasks.due_date IS NOT NULL AND tasks.due_date < ?
                          THEN 1 ELSE 0 END) as overdue_count',
                [$todayDate]
            )
            ->first();

        // "Completed this week" doesn't share the open-scope filters (a
        // done/cancelled status IS relevant here), so it stays a separate
        // count against a different index (assigned_to, completed_at).
        $completedThisWeek = Task::query()
            ->where('assigned_to', $employee->id)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [
                $today->startOfWeek()->toDateTimeString(),
                $today->endOfWeek()->toDateTimeString(),
            ])
            ->count();

        return [
            'open' => (int) ($open->open_count ?? 0),
            'in_progress' => (int) ($open->in_progress_count ?? 0),
            'overdue' => (int) ($open->overdue_count ?? 0),
            'completed_this_week' => $completedThisWeek,
        ];
    }

    /**
     * @return array{
     *   today: array{status: null, checked_in_at: null, checked_out_at: null, open_session_since: null},
     *   month: array{present: 0, late: 0, absent: 0, leave: 0, working_days_elapsed: 0},
     *   leaves: array{pending: 0, upcoming: null, balances: array<int, mixed>},
     *   requests: array{pending: 0},
     *   tasks: array{open: 0, in_progress: 0, overdue: 0, completed_this_week: 0},
     * }
     */
    private function empty(CarbonImmutable $today): array
    {
        return [
            'today' => [
                'status' => null,
                'checked_in_at' => null,
                'checked_out_at' => null,
                'open_session_since' => null,
            ],
            'month' => ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'working_days_elapsed' => 0],
            'leaves' => ['pending' => 0, 'upcoming' => null, 'balances' => []],
            'requests' => ['pending' => 0],
            'tasks' => ['open' => 0, 'in_progress' => 0, 'overdue' => 0, 'completed_this_week' => 0],
        ];
    }
}
