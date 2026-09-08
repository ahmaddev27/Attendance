<?php

declare(strict_types=1);

namespace App\Modules\AI\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Task;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\LeaveStatus;
use Carbon\CarbonImmutable;

/**
 * Gathers the small numeric snapshot the LLM needs to write a
 * personal motivational line for one employee. Kept as a plain
 * builder — one method in, a typed array out — so it stays trivial
 * to unit test and cache the result upstream without dragging any
 * HTTP concerns into the query layer.
 *
 * Every count is a scoped aggregate (no joins across modules), so
 * building context for a single employee is O(a handful of small
 * queries) and safe to call inline on a dashboard request.
 */
class MotivationContextBuilder
{
    /**
     * @return array{
     *   employee_name: string,
     *   attendance_streak: int,
     *   tasks_completed_this_week: int,
     *   open_tasks: int,
     *   upcoming_leave: array{start_date: string, end_date: string, leave_type: ?string}|null,
     *   total_attendance_days_this_month: int,
     * }
     */
    public function build(Employee $employee, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now()->startOfDay();

        return [
            'employee_name' => $employee->full_name,
            'attendance_streak' => $this->attendanceStreak($employee, $today),
            'tasks_completed_this_week' => $this->tasksCompletedThisWeek($employee, $today),
            'open_tasks' => $this->openTasks($employee),
            'upcoming_leave' => $this->upcomingLeave($employee, $today),
            'total_attendance_days_this_month' => $this->totalAttendanceDaysThisMonth($employee, $today),
        ];
    }

    /**
     * Consecutive days in the current month, walking backwards from
     * today, on which the employee was Present or Late. Stops at the
     * first day that isn't one of those two — a weekend/holiday/absence
     * all break the streak, which is intentionally the strictest read
     * (the motivation copy should reward true consistency, not "was
     * around when the office was open").
     */
    private function attendanceStreak(Employee $employee, CarbonImmutable $today): int
    {
        $monthStart = $today->startOfMonth();
        $countedStatuses = [
            AttendanceStatus::Present->value,
            AttendanceStatus::Late->value,
        ];

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()])
            ->orderByDesc('date')
            ->get(['date', 'status'])
            ->keyBy(fn (Attendance $a): string => $a->date->toDateString());

        $streak = 0;
        for ($cursor = $today; $cursor->greaterThanOrEqualTo($monthStart); $cursor = $cursor->subDay()) {
            $record = $records->get($cursor->toDateString());
            if ($record === null) {
                break;
            }

            $status = $record->status instanceof AttendanceStatus
                ? $record->status->value
                : (string) $record->status;

            if (! in_array($status, $countedStatuses, true)) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    private function tasksCompletedThisWeek(Employee $employee, CarbonImmutable $today): int
    {
        $weekStart = $today->startOfWeek();

        return Task::query()
            ->where('assigned_to', $employee->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $weekStart)
            ->count();
    }

    /**
     * Tasks still on the employee's plate. We exclude anything in a
     * done- or cancelled-flagged status via the join on task_statuses,
     * mirroring how AdminDashboardService counts "open" tasks — so
     * admin-defined statuses that are semantically closed don't leak
     * in here as "open" motivation fodder.
     */
    private function openTasks(Employee $employee): int
    {
        return Task::query()
            ->where('assigned_to', $employee->id)
            ->whereHas('status', function ($q): void {
                $q->where('is_done_state', false)
                    ->where('is_cancelled_state', false);
            })
            ->count();
    }

    /**
     * @return array{start_date: string, end_date: string, leave_type: ?string}|null
     */
    private function upcomingLeave(Employee $employee, CarbonImmutable $today): ?array
    {
        $leave = LeaveRequest::query()
            ->with('leaveType:id,name')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::Approved->value)
            ->where('start_date', '>=', $today->toDateString())
            ->orderBy('start_date')
            ->first();

        if ($leave === null) {
            return null;
        }

        return [
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'leave_type' => $leave->leaveType?->name,
        ];
    }

    private function totalAttendanceDaysThisMonth(Employee $employee, CarbonImmutable $today): int
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$today->startOfMonth()->toDateString(), $today->toDateString()])
            ->whereIn('status', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
            ])
            ->count();
    }
}
