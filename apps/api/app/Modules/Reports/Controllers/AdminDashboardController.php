<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;

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
     */
    public function kpis(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->kpis(),
        ]);
    }
}
