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
     * Cached for 15s per-user — short enough that a task the user just
     * completed or a scan they just made reflects on the next KPI
     * refresh, still long enough to collapse SPA-re-entry / tab-focus
     * refetch bursts into a single set of queries.
     */
    public function kpis(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => Cache::remember(
                "me.kpis:{$user->id}",
                15,
                fn () => $this->dashboard->forUser($user),
            ),
        ]);
    }
}
