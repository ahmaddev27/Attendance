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
use App\Shared\Enums\EmployeeStatus;
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
     * `POST /api/scan/status` — the kiosk asks: "what's the next action
     * for this employee at this device?" Returns exactly one of:
     *   - not_checked_in   → today's row has no check_in_at → show
     *                        "تسجيل حضور" button
     *   - checked_in       → check_in_at set + check_out_at null → show
     *                        "تسجيل انصراف" button
     *   - checked_out      → both set → today's cycle done, show a
     *                        friendly "done for today" message
     *
     * The frontend uses this to auto-pick the correct button instead of
     * asking the employee to guess. QR token + employee_number are the
     * same auth as check-in/out — no session, kiosk-mode by design.
     */
    public function status(\App\Modules\Attendance\Requests\ScanStatusRequest $request): JsonResponse
    {
        try {
            $device = $this->qrTokens->resolveDevice($request->qrToken());

            $employee = Employee::query()
                ->where('employee_number', $request->employeeNumber())
                ->where('status', EmployeeStatus::Active)
                ->first();

            if (! $employee) {
                throw new InvalidScanCredentialsException('Unknown employee number.');
            }

            $today = now()->toDateString();
            $attendance = \App\Models\Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today)
                ->first();

            $state = match (true) {
                $attendance === null || $attendance->check_in_at === null => 'not_checked_in',
                $attendance->check_out_at === null => 'checked_in',
                default => 'checked_out',
            };

            // NB: deliberately do NOT return the employee's full_name here.
            // /scan/status is a public, unauthenticated endpoint gated only
            // by (qr_token, employee_number); returning the name would let
            // anyone holding a valid kiosk QR walk the employee_number
            // space and enumerate the entire staff directory. The FE
            // reveals the name only AFTER a successful check-in POST,
            // which additionally passes the FraudGuard checks.
            return response()->json([
                'data' => [
                    'state' => $state,
                    'employee' => [
                        'id' => $employee->id,
                        'employee_number' => $employee->employee_number,
                    ],
                    'check_in_at' => $attendance?->check_in_at?->toIso8601String(),
                    'check_out_at' => $attendance?->check_out_at?->toIso8601String(),
                    'device_name' => $device->name,
                ],
            ]);
        } catch (AttendanceModuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->statusCode());
        }
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
            // Enforcement flags the FE needs to decide whether to trigger
            // the browser's geolocation prompt. When enforce_geo is off
            // we don't ask the browser for GPS at all — asking every time
            // and then discarding the answer is a noisy UX.
            'enforce_geo' => (bool) $device->enforce_geo,
            'enforce_ip' => (bool) $device->enforce_ip,
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

            // Only ACTIVE employees may scan. A terminated/inactive/on-leave
            // employee whose card is still floating around should NOT be
            // able to stamp attendance rows. The status filter is applied
            // in-query so we don't leak "this number exists but isn't
            // active" via a distinguishable second error.
            $employee = Employee::query()
                ->where('employee_number', $request->employeeNumber())
                ->where('status', EmployeeStatus::Active)
                ->first();

            if (! $employee) {
                throw new InvalidScanCredentialsException('Unknown employee number.');
            }

            // Merged with "unknown number" to avoid an enumeration oracle:
            // an attacker with a valid kiosk QR token would otherwise be
            // able to walk `employee_number` and distinguish
            // "no such number" (200/422) from "number exists but disabled"
            // (403), enumerating the terminated-employee directory.
            if ($employee->user && ! $employee->user->is_active) {
                throw new InvalidScanCredentialsException('Unknown employee number.');
            }

            $attendance = $action($employee, $device);

            // FE contract: `{ attendance, message }`. We wrap the resource
            // ourselves rather than let `AttendanceResource` bind it under
            // `data`, so the kiosk can read `attendance.employee.*`
            // directly for the success card.
            return response()->json([
                'attendance' => (new AttendanceResource($attendance))->resolve(),
                'message' => 'تم تسجيل العملية بنجاح',
            ]);
        } catch (AttendanceModuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->statusCode());
        }
    }
}
