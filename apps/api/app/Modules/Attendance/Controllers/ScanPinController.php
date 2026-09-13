<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Attendance\Requests\UpdateScanPinEnforcementRequest;
use App\Modules\Attendance\Services\ScanPinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin surface for rolling scan PINs out. reset() is the only endpoint in
 * the API that ever returns a plaintext PIN.
 */
class ScanPinController extends Controller
{
    public function __construct(
        private readonly ScanPinService $scanPins,
    ) {}

    /**
     * `GET /api/admin/attendance/scan-pins`
     */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->scanPins->summary()]);
    }

    /**
     * `POST /api/admin/attendance/scan-pins/issue-missing`
     */
    public function issueMissing(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->scanPins->issueMissing($request->user())]);
    }

    /**
     * `PUT /api/admin/attendance/scan-pins/enforcement`
     */
    public function updateEnforcement(UpdateScanPinEnforcementRequest $request): JsonResponse
    {
        $this->scanPins->setRequired($request->pinRequired(), $request->force(), $request->user());

        return response()->json(['data' => $this->scanPins->summary()]);
    }

    /**
     * `POST /api/employees/{employee}/scan-pin`
     *
     * no-store: this body carries a live credential and must not be kept
     * by the browser or any proxy cache.
     */
    public function reset(Request $request, Employee $employee): JsonResponse
    {
        return response()
            ->json(['data' => $this->scanPins->reset($employee, $request->user())])
            ->header('Cache-Control', 'no-store');
    }
}
