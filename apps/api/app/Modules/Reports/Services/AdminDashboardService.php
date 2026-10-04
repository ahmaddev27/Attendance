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
    public function kpis(?CarbonImmutable $today = null, ?int $companyId = null): array
    {
        $today ??= CarbonImmutable::now()->startOfDay();

        return [
            'employees' => $this->employeeCounts($companyId),
            'today' => $this->todayAttendance($today, $companyId),
            'pending' => $this->pendingApprovals($companyId),
            'tasks' => $this->taskCounts($today, $companyId),
        ];
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    private function employeeCounts(?int $companyId): array
    {
        // Single grouped query — one row per EmployeeStatus value (active,
        // inactive, on_leave, terminated). We collapse everything that
        // isn't 'active' into the inactive bucket for the KPI card, since
        // the sidebar's own filter treats on_leave/terminated as
        // "temporarily not around" rather than a separate cohort.
        $rows = Employee::query()
            ->staffOnly()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
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
    private function todayAttendance(CarbonImmutable $today, ?int $companyId): array
    {
        // Half-open range on `date` instead of a bare equality — the
        // Laravel `date` cast stores "YYYY-MM-DD 00:00:00" on SQLite, so
        // `where('date', '2026-10-03')` matches nothing and the whole
        // widget silently reads zeros. AttendanceService::checkIn,
        // ScanController::record and ScanController::status all use the
        // same pattern (last fixed in commit `50e4e63`).
        $counts = Attendance::query()
            ->where('date', '>=', $today)
            ->where('date', '<', $today->addDay())
            ->when($companyId, function ($q) use ($companyId): void {
                $q->whereIn(
                    'employee_id',
                    Employee::withTrashed()->select('id')->where('company_id', $companyId),
                );
            })
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        // Owner's rule: a late check-in STILL means the person showed up,
        // so they count toward both `present` AND the `late` sub-count.
        // The two numbers answer different questions on the dashboard —
        // "how many are here?" vs "how many came in late?" — and the
        // business reads them that way, not as mutually exclusive buckets.
        $late = (int) ($counts[AttendanceStatus::Late->value] ?? 0)
            + (int) ($counts[AttendanceStatus::EarlyLeave->value] ?? 0);

        $present = (int) ($counts[AttendanceStatus::Present->value] ?? 0)
            + (int) ($counts[AttendanceStatus::Remote->value] ?? 0)
            + (int) ($counts[AttendanceStatus::BusinessMission->value] ?? 0)
            + $late;

        $onLeave = (int) ($counts[AttendanceStatus::OnLeave->value] ?? 0);
        $explicitAbsent = (int) ($counts[AttendanceStatus::Absent->value] ?? 0);

        // Owner's rule 2026-10-04: "absent" on the live dashboard should
        // read as "active staff who didn't show up and aren't on leave",
        // not just the Absent-flagged rows the nightly engine writes at
        // end of day. Earlier the number sat at 0 all morning because
        // nobody is Absent-stamped until midnight. Derive it from the
        // active headcount instead so it reflects reality in real time.
        $activeHeadcount = Employee::query()
            ->staffOnly()
            ->where('status', EmployeeStatus::Active->value)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $derivedAbsent = max(0, $activeHeadcount - $present - $onLeave);

        return [
            'date' => $today->toDateString(),
            'present' => $present,
            'late' => $late,
            // Prefer the nightly engine's explicit Absent rows once they
            // exist (end of day — authoritative because it respects
            // weekends + holidays + per-employee schedules). Fall back to
            // the live derivation during the day so the number never
            // looks stuck at zero.
            'absent' => $explicitAbsent > 0 ? $explicitAbsent : $derivedAbsent,
            'on_leave' => $onLeave,
        ];
    }

    /**
     * @return array{leaves: int, requests: int, total: int}
     */
    private function pendingApprovals(?int $companyId): array
    {
        $employeeFilter = static function ($q) use ($companyId): void {
            if ($companyId === null) {
                return;
            }
            $q->whereIn(
                'employee_id',
                Employee::withTrashed()->select('id')->where('company_id', $companyId),
            );
        };

        $leaves = LeaveRequest::query()
            ->where('status', LeaveStatus::Pending->value)
            ->tap($employeeFilter)
            ->count();

        $requests = RequestModel::query()
            ->where('status', RequestStatus::Pending->value)
            ->tap($employeeFilter)
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
    private function taskCounts(CarbonImmutable $today, ?int $companyId): array
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
            })
            ->when($companyId, function ($q) use ($companyId): void {
                $companyEmployees = Employee::withTrashed()->select('id')->where('company_id', $companyId);
                $q->where(function ($w) use ($companyEmployees): void {
                    $w->whereIn('created_by', (clone $companyEmployees))
                        ->orWhereIn('assigned_to', (clone $companyEmployees));
                });
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
