<?php

declare(strict_types=1);

namespace App\Modules\System\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\System\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(private readonly HealthCheckService $health) {}

    /**
     * GET /api/health — 200 while the API can serve requests (ok or
     * degraded), 503 when a critical dependency is down.
     */
    public function __invoke(): JsonResponse
    {
        $report = $this->health->run();
        $httpStatus = $report['status'] === HealthCheckService::STATUS_DOWN ? 503 : 200;

        return response()
            ->json([...$report, 'time' => now()->toIso8601String()], $httpStatus)
            ->header('Cache-Control', 'no-store');
    }
}
