<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\EmployeeDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     */
    public function kpis(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->forUser($request->user()),
        ]);
    }
}
