<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Requests\AnalyticsFiltersRequest;
use App\Modules\Analytics\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;

/**
 * Thin coordinator. All five endpoints share the same request-validation
 * class (AnalyticsFiltersRequest) and return the same envelope shape
 * ({data, meta}), so the controller is basically a dispatch table — every
 * bit of computation lives in AnalyticsService.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $service)
    {
    }

    public function attendanceKpis(AnalyticsFiltersRequest $request): JsonResponse
    {
        return response()->json($this->service->attendanceKpis($request));
    }

    public function attendanceHeatmap(AnalyticsFiltersRequest $request): JsonResponse
    {
        return response()->json($this->service->attendanceHeatmap($request));
    }

    public function leavePatterns(AnalyticsFiltersRequest $request): JsonResponse
    {
        return response()->json($this->service->leavePatterns($request));
    }

    public function taskPerformance(AnalyticsFiltersRequest $request): JsonResponse
    {
        return response()->json($this->service->taskPerformance($request));
    }

    public function employeeSummary(AnalyticsFiltersRequest $request): JsonResponse
    {
        return response()->json($this->service->employeeSummary($request));
    }
}
