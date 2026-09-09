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
     *   today: array{status: string|null, checked_in_at: string|null, checked_out_at: string|null},
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
     * @return array{status: string|null, checked_in_at: string|null, checked_out_at: string|null}
     */
    private function today(Employee $employee, CarbonImmutable $today): array
    {
        $record = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today->toDateString())
            ->first();

        if (! $record) {
            return ['status' => null, 'checked_in_at' => null, 'checked_out_at' => null];
        }

        return [
            'status' => $record->status?->value,
            'checked_in_at' => $record->check_in_at?->toIso8601String(),
            'checked_out_at' => $record->check_out_at?->toIso8601String(),
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
            ->whereDate('start_date', '>=', $today->toDateString())
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
        $baseOpen = Task::query()
            ->where('assigned_to', $employee->id)
            ->whereHas('status', function ($q): void {
                $q->where('is_done_state', false)->where('is_cancelled_state', false);
            });

        $open = (clone $baseOpen)->count();
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

        $completedThisWeek = Task::query()
            ->where('assigned_to', $employee->id)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [
                $today->startOfWeek()->toDateTimeString(),
                $today->endOfWeek()->toDateTimeString(),
            ])
            ->count();

        return [
            'open' => $open,
            'in_progress' => $inProgress,
            'overdue' => $overdue,
            'completed_this_week' => $completedThisWeek,
        ];
    }

    /**
     * @return array{
     *   today: array{status: null, checked_in_at: null, checked_out_at: null},
     *   month: array{present: 0, late: 0, absent: 0, leave: 0, working_days_elapsed: 0},
     *   leaves: array{pending: 0, upcoming: null, balances: array<int, mixed>},
     *   requests: array{pending: 0},
     *   tasks: array{open: 0, in_progress: 0, overdue: 0, completed_this_week: 0},
     * }
     */
    private function empty(CarbonImmutable $today): array
    {
        return [
            'today' => ['status' => null, 'checked_in_at' => null, 'checked_out_at' => null],
            'month' => ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'working_days_elapsed' => 0],
            'leaves' => ['pending' => 0, 'upcoming' => null, 'balances' => []],
            'requests' => ['pending' => 0],
            'tasks' => ['open' => 0, 'in_progress' => 0, 'overdue' => 0, 'completed_this_week' => 0],
        ];
    }
}
