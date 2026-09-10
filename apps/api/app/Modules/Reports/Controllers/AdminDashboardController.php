<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboard,
    ) {}

    /**
     * GET /api/admin/dashboard/kpis
     *
     * Returns the KPI block for the admin home page. Wrapped in `data`
     * to match every other endpoint in the API (see EmployeeResource et
     * al) so the frontend's response envelope is one shape.
     *
     * Cached org-wide for 15s — short enough that a task change or a
     * check-in reflects in the numbers on the next dashboard refresh
     * (users noticed a 60s window was surprising when a task they just
     * marked complete still showed as open). The shared entry still
     * absorbs the concurrent-admin refresh burst inside each window.
     */
    public function kpis(): JsonResponse
    {
        return response()->json([
            'data' => Cache::remember(
                'admin.kpis',
                15,
                fn () => $this->dashboard->kpis(),
            ),
        ]);
    }
}
