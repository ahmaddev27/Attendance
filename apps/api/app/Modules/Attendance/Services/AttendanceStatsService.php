<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Support\IpMatcher;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregates attendance rows for the admin list page's stat tiles.
 *
 * Mirrors the filter set accepted by AttendanceRepository::paginate so the
 * stat row and the table are always counting the same slice of data, with
 * one addition — origin counts (onsite / remote / unknown) which the
 * resource layer surfaces per-row and are totalled here in-process to
 * reuse the same IpMatcher logic without duplicating SQL for whitelist
 * membership (which would be a bespoke join across a JSON column).
 */
class AttendanceStatsService
{
    /**
     * @param  array{employee_id?: int, status?: string, date_from?: string, date_to?: string, company_id?: int|null, department_id?: int|null}  $filters
     * @return array<string, int|float>
     */
    public function summarise(array $filters): array
    {
        $query = $this->buildBaseQuery($filters);

        // Status + hours aggregates in one SQL call. GROUP BY status to avoid
        // nine COUNT(CASE WHEN...) scans on potentially large ranges.
        $statusAggregates = (clone $query)
            ->selectRaw('status, COUNT(*) as rows_count, COALESCE(SUM(total_minutes), 0) as total_minutes, COALESCE(SUM(overtime_minutes), 0) as overtime_minutes')
            ->groupBy('status')
            ->get();

        $totals = [
            'total_rows' => 0,
            'present_count' => 0,
            'absent_count' => 0,
            'late_count' => 0,
            'on_leave_count' => 0,
            'holiday_count' => 0,
            'weekend_count' => 0,
            'total_minutes' => 0,
            'total_overtime_minutes' => 0,
        ];

        foreach ($statusAggregates as $row) {
            $rowsCount = (int) $row->rows_count;
            $totals['total_rows'] += $rowsCount;
            $totals['total_minutes'] += (int) $row->total_minutes;
            $totals['total_overtime_minutes'] += (int) $row->overtime_minutes;

            // The Attendance model casts `status` to the enum, so the
            // raw-selected column arrives as an AttendanceStatus instance.
            // Normalise to its string value before bucketing.
            $statusValue = $row->status instanceof AttendanceStatus ? $row->status->value : $row->status;
            $totals[$this->bucketFor($statusValue)] += $rowsCount;
        }

        $uniqueEmployees = (clone $query)->distinct('employee_id')->count('employee_id');

        $originCounts = $this->countByOrigin($query);

        $totalHours = round($totals['total_minutes'] / 60, 2);
        $totalOvertimeHours = round($totals['total_overtime_minutes'] / 60, 2);

        // "Days with hours" is more honest than total_rows for an hourly
        // average — leave/holiday/absent rows carry zero minutes and would
        // drag the mean to the floor. Use the sum of (present + late +
        // early_leave) buckets instead.
        $hoursBearingRows = $totals['present_count']
            + ($totals['late_count'] ?? 0);

        $avgHoursPerDay = $hoursBearingRows > 0
            ? round($totals['total_minutes'] / 60 / $hoursBearingRows, 2)
            : 0.0;

        return [
            'total_rows' => $totals['total_rows'],
            'unique_employees' => $uniqueEmployees,
            'present_count' => $totals['present_count'],
            'absent_count' => $totals['absent_count'],
            'late_count' => $totals['late_count'],
            'on_leave_count' => $totals['on_leave_count'],
            'holiday_count' => $totals['holiday_count'],
            'weekend_count' => $totals['weekend_count'],
            'total_hours' => $totalHours,
            'total_overtime_hours' => $totalOvertimeHours,
            'avg_hours_per_day' => $avgHoursPerDay,
            'onsite_count' => $originCounts['onsite'],
            'remote_count' => $originCounts['remote'],
            'unknown_origin_count' => $originCounts['unknown'],
        ];
    }

    /**
     * Only the three primary buckets the stat row surfaces claim a counter;
     * secondary statuses collapse into the closest category so the counts
     * add up to total_rows without inventing new tiles.
     */
    private function bucketFor(?string $status): string
    {
        return match ($status) {
            AttendanceStatus::Present->value, AttendanceStatus::Remote->value, AttendanceStatus::BusinessMission->value => 'present_count',
            AttendanceStatus::Late->value, AttendanceStatus::EarlyLeave->value => 'late_count',
            AttendanceStatus::Absent->value => 'absent_count',
            AttendanceStatus::OnLeave->value => 'on_leave_count',
            AttendanceStatus::Holiday->value => 'holiday_count',
            AttendanceStatus::Weekend->value => 'weekend_count',
            default => 'present_count',
        };
    }

    /**
     * Origin counts can't be derived from a simple COUNT because the
     * whitelist is a JSON column on attendance_devices with per-row CIDR
     * membership rules. Pulling the slim (ip, whitelist) tuples and
     * delegating to IpMatcher in-process keeps the logic in one place and
     * stays within acceptable memory bounds for admin stat tiles.
     *
     * @param  Builder<Attendance>  $query
     * @return array{onsite: int, remote: int, unknown: int}
     */
    private function countByOrigin(Builder $query): array
    {
        $counts = ['onsite' => 0, 'remote' => 0, 'unknown' => 0];

        (clone $query)
            ->leftJoin('attendance_devices', 'attendances.check_in_device_id', '=', 'attendance_devices.id')
            ->selectRaw('attendances.check_in_ip as ip, attendance_devices.ip_whitelist as whitelist')
            ->orderBy('attendances.id')
            ->chunk(1000, function ($rows) use (&$counts): void {
                foreach ($rows as $row) {
                    $counts[$this->originFor($row->ip, $row->whitelist)]++;
                }
            });

        return $counts;
    }

    private function originFor(?string $ip, ?string $whitelistJson): string
    {
        if ($ip === null || $whitelistJson === null) {
            return 'unknown';
        }

        /** @var array<int, string>|null $whitelist */
        $whitelist = json_decode($whitelistJson, true);

        if (! is_array($whitelist) || empty($whitelist)) {
            return 'unknown';
        }

        return IpMatcher::matchesAny($ip, $whitelist) ? 'onsite' : 'remote';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Attendance>
     */
    private function buildBaseQuery(array $filters): Builder
    {
        return Attendance::query()
            ->when($filters['employee_id'] ?? null, fn (Builder $q, $employeeId) => $q->where('employee_id', $employeeId))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            // date_from / date_to normalised to datetime bounds — the
            // Laravel `date` cast stores "YYYY-MM-DD 00:00:00" on SQLite,
            // so a naive `<= '2026-10-03'` compares as string and
            // EXCLUDES rows at `2026-10-03 00:00:00` (which are strictly
            // greater). The default admin filter is today→today, so the
            // bug would land as "stat tiles always zero" in that case.
            // Normalising to full-day bounds also matches
            // AdminDashboardService::todayAttendance and
            // ScanController::record (last fixed in commits 95a2cd2 /
            // 50e4e63).
            ->when(
                $filters['date_from'] ?? null,
                fn (Builder $q, $date) => $q->where('date', '>=', \Carbon\Carbon::parse((string) $date)->startOfDay()),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn (Builder $q, $date) => $q->where('date', '<', \Carbon\Carbon::parse((string) $date)->startOfDay()->addDay()),
            )
            ->when(! empty($filters['company_id']), fn (Builder $q) => $q->whereIn(
                'employee_id',
                Employee::withTrashed()->select('id')->where('company_id', $filters['company_id']),
            ))
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->whereIn(
                'employee_id',
                Employee::withTrashed()->select('id')->where('department_id', $filters['department_id']),
            ));
    }
}
