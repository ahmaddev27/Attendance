<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Requests\UpdateMyScanPinRequest;
use App\Modules\Attendance\Services\ScanPinService;
use Illuminate\Http\Response;

/**
 * The employee is always resolved from the session, never from client
 * input, so nobody can set a colleague's PIN through this endpoint.
 */
class MyScanPinController extends Controller
{
    public function __construct(
        private readonly ScanPinService $scanPins,
    ) {}

    /**
     * `PUT /api/me/scan-pin`
     */
    public function update(UpdateMyScanPinRequest $request): Response
    {
        $user = $request->user();

        $this->scanPins->change($user->employee, $request->currentPassword(), $request->pin(), $user);

        return response()->noContent();
    }
}
