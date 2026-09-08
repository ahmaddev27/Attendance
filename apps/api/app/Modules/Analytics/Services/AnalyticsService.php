<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Requests\AnalyticsFiltersRequest;
use Illuminate\Support\Facades\Cache;
use Carbon\CarbonImmutable;

/**
 * Public entry-point for every analytics endpoint. Each method here maps
 * one-to-one with an HTTP route on AnalyticsController — the controller
 * stays a thin coordinator and never touches the four specialised
 * services below directly.
 *
 * Caching is intentionally centralised here rather than per-service:
 *   • one TTL policy (5 min) to reason about
 *   • one bump point (`::VERSION`) invalidates every card at once when
 *     the query shape changes
 *   • services stay stateless so they're trivially unit-testable
 *
 * Cache key = `analytics:vN:{endpoint}:{sha1(filters)}`. Two callers with
 * identical filters share one hydrated row across services and across
 * users — analytics is org-wide by design, no per-user privacy layer to
 * protect against (dept/team/employee filters are user-supplied narrowers,
 * not authorisation boundaries; the `view-reports` permission gate at the
 * route level is where authorisation lives).
 */
class AnalyticsService
{
    /**
     * Cache namespace version. Bump when any downstream service changes
     * the shape it returns so the frontend never reads a stale key that
     * would deserialize into a broken UI.
     */
    private const VERSION = 'v1';

    private const CACHE_TTL = 300; // 5 min

    public function __construct(
        private readonly AttendanceAnalyticsService $attendance,
        private readonly LeaveAnalyticsService $leaves,
        private readonly TaskAnalyticsService $tasks,
        private readonly EmployeeAnalyticsService $employees,
    ) {
    }

    public function attendanceKpis(AnalyticsFiltersRequest $filters): array
    {
        return $this->cached('attendance.kpis', $filters, fn () => $this->attendance->kpis($filters));
    }

    public function attendanceHeatmap(AnalyticsFiltersRequest $filters): array
    {
        return $this->cached('attendance.heatmap', $filters, fn () => $this->attendance->heatmap($filters));
    }

    public function leavePatterns(AnalyticsFiltersRequest $filters): array
    {
        return $this->cached('leaves.patterns', $filters, fn () => $this->leaves->patterns($filters));
    }

    public function taskPerformance(AnalyticsFiltersRequest $filters): array
    {
        return $this->cached('tasks.performance', $filters, fn () => $this->tasks->performance($filters));
    }

    public function employeeSummary(AnalyticsFiltersRequest $filters): array
    {
        return $this->cached('employees.summary', $filters, fn () => $this->employees->summary($filters));
    }

    /**
     * Wrap a lookup with Cache::remember and attach a `meta.cached_at`
     * timestamp to the response — the frontend renders it as "آخر تحديث"
     * so the user knows how fresh the numbers are without triggering a
     * manual refetch. Timestamp is written on cache-write; a hit returns
     * the original write time.
     */
    private function cached(string $endpoint, AnalyticsFiltersRequest $filters, \Closure $compute): array
    {
        $key = sprintf('analytics:%s:%s:%s', self::VERSION, $endpoint, $filters->cacheKey());

        return Cache::remember($key, self::CACHE_TTL, function () use ($compute, $filters) {
            return [
                'data' => $compute(),
                'meta' => [
                    'from' => $filters->from()->toDateString(),
                    'to' => $filters->to()->toDateString(),
                    'department_id' => $filters->departmentId(),
                    'team_id' => $filters->teamId(),
                    'employee_id' => $filters->employeeId(),
                    'cached_at' => CarbonImmutable::now()->toIso8601String(),
                ],
            ];
        });
    }
}
