<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Modules\Attendance\Exceptions\AttendanceModuleException;
use App\Modules\Attendance\Exceptions\InvalidScanCredentialsException;
use App\Modules\Attendance\Repositories\AttendanceDeviceRepository;
use App\Modules\Attendance\Requests\ScanRequest;
use App\Modules\Attendance\Resources\AttendanceResource;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Attendance\Services\QrTokenService;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated endpoints hit by the kiosk/mobile scan flow. The
 * QR token (device credential) plus employee_number (employee credential)
 * stand in for auth:sanctum here — see FraudGuardService and
 * QrTokenService for how each is verified.
 */
class ScanController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly QrTokenService $qrTokens,
        private readonly AttendanceDeviceRepository $devices,
    ) {}

    public function checkIn(ScanRequest $request): JsonResponse
    {
        return $this->handleScan($request, fn (Employee $employee, AttendanceDevice $device) => $this->attendanceService->checkIn(
            $employee,
            $device,
            $request->latitude(),
            $request->longitude(),
            $request->ip() ?? '0.0.0.0',
        ));
    }

    public function checkOut(ScanRequest $request): JsonResponse
    {
        return $this->handleScan($request, fn (Employee $employee, AttendanceDevice $device) => $this->attendanceService->checkOut(
            $employee,
            $device,
            $request->latitude(),
            $request->longitude(),
            $request->ip() ?? '0.0.0.0',
        ));
    }

    /**
     * Lightweight endpoint for the kiosk display: confirms the token is
     * still valid and returns the server clock, without requiring an
     * employee number.
     */
    public function deviceInfo(string $qrToken): JsonResponse
    {
        $device = $this->devices->findActiveByToken($qrToken);

        if (! $device) {
            return response()->json(['message' => 'Device not found or inactive.'], 404);
        }

        $secondsSinceRotation = (int) ($device->last_token_rotated_at?->diffInSeconds(now()) ?? $device->qr_rotates_every_seconds);

        return response()->json([
            'device_name' => $device->name,
            'server_time' => now()->toIso8601String(),
            'token_expires_in' => max(0, $device->qr_rotates_every_seconds - $secondsSinceRotation),
        ]);
    }

    /**
     * Shared plumbing for check-in/check-out: resolve the device from the
     * QR token, resolve the employee from their number, run the given
     * action, and translate any module exception into its HTTP response.
     *
     * @param  \Closure(Employee, AttendanceDevice): Attendance  $action
     */
    private function handleScan(ScanRequest $request, \Closure $action): JsonResponse
    {
        try {
            $device = $this->qrTokens->resolveDevice($request->qrToken());

            $employee = Employee::query()
                ->where('employee_number', $request->employeeNumber())
                ->first();

            if (! $employee) {
                throw new InvalidScanCredentialsException('Unknown employee number.');
            }

            $attendance = $action($employee, $device);

            return (new AttendanceResource($attendance))
                ->response()
                ->setStatusCode(200);
        } catch (AttendanceModuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->statusCode());
        }
    }
}
