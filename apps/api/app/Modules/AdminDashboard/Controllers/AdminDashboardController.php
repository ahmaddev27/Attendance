<?php

declare(strict_types=1);

namespace App\Modules\AdminDashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminDashboard\Services\ExecutiveDashboardAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin HTTP layer over ExecutiveDashboardAggregator — kept as a real
 * controller (rather than the inline route closures sketched in the M8
 * brief) to stay consistent with every other module's
 * Controller -> Service layering and to keep these endpoints testable in
 * isolation from routing.
 */
class AdminDashboardController extends Controller
{
    private const DEFAULT_TREND_DAYS = 7;

    public function __construct(
        private readonly ExecutiveDashboardAggregator $aggregator,
    ) {}

    public function summary(): JsonResponse
    {
        return response()->json($this->aggregator->summary());
    }

    public function attendanceTrend(Request $request): JsonResponse
    {
        return response()->json(
            $this->aggregator->attendanceTrend((int) $request->integer('days', self::DEFAULT_TREND_DAYS))
        );
    }

    public function departments(Request $request): JsonResponse
    {
        $departmentId = $request->query('department_id');

        return response()->json(
            $this->aggregator->departmentPerformance($departmentId !== null ? (int) $departmentId : null)
        );
    }
}
