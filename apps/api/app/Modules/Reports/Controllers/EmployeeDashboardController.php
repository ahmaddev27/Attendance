<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\EmployeeDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmployeeDashboardController extends Controller
{
    public function __construct(
        private readonly EmployeeDashboardService $dashboard,
    ) {}

    /**
     * GET /api/me/dashboard/kpis — personal KPIs for the signed-in user.
     *
     * Deliberately unguarded by permission middleware — every authenticated
     * user has a right to see their own numbers. The service scopes every
     * query to $user->employee at the DB layer, so no cross-user leaks
     * are possible even if the caller forgets.
     *
     * Cached for 30s per-user to keep the KPI card cheap on repeat loads
     * (SPA route re-entry, tab focus refetches, react-query background
     * pings). The service issues ~10 queries per call; a 30s TTL keeps the
     * numbers effectively "live" while collapsing bursty traffic.
     */
    public function kpis(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => Cache::remember(
                "me.kpis:{$user->id}",
                30,
                fn () => $this->dashboard->forUser($user),
            ),
        ]);
    }
}
