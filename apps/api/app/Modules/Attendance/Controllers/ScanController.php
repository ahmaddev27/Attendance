<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Modules\Attendance\Exceptions\AttendanceModuleException;
use App\Modules\Attendance\Repositories\AttendanceDeviceRepository;
use App\Modules\Attendance\Requests\ScanIdentityRequest;
use App\Modules\Attendance\Requests\ScanRequest;
use App\Modules\Attendance\Requests\ScanStatusRequest;
use App\Modules\Attendance\Resources\AttendanceResource;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Attendance\Services\QrTokenService;
use App\Modules\Attendance\Services\ScanIdentityService;
use App\Modules\Attendance\Services\ScanPinService;
use Illuminate\Http\JsonResponse;

/**
 * Public endpoints hit by the kiosk page and the mobile app. The QR token is
 * the device credential; the employee is proven either by a bearer token
 * (mobile) or by employee number plus scan PIN (kiosk) — see
 * ScanIdentityService, QrTokenService and FraudGuardService.
 */
class ScanController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly QrTokenService $qrTokens,
        private readonly ScanIdentityService $scanIdentity,
        private readonly ScanPinService $scanPins,
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
     * `POST /api/scan/record` — one-tap kiosk endpoint for PIN-only mode:
     * resolves the employee from the typed PIN, reads today's state, and
     * executes the one appropriate action without a confirmation screen.
     *
     * This is what makes the "PIN فقط بدون تأكيد ولا اسم" UX possible
     * (see memory: project-pin-only-scan-decision) — the FE sends PIN,
     * the server picks check-in vs check-out, both parties learn the
     * result in a single round trip. If the day is already complete, we
     * return 409 so the kiosk can show a friendly "done for today".
     */
    public function record(ScanRequest $request): JsonResponse
    {
        try {
            $device = $this->qrTokens->resolveDevice($request->qrToken());
            $employee = $this->resolveEmployee($request);

            // Half-open range on `date` (not a bare equality) because the
            // Laravel `date` cast stores "YYYY-MM-DD 00:00:00" on SQLite
            // — a string comparison against the ISO date misses every
            // row and we'd mis-route the second scan as a brand-new
            // check-in. AttendanceService::checkIn uses the same pattern.
            $today = now()->startOfDay();
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('date', '>=', $today)
                ->where('date', '<', $today->copy()->addDay())
                ->first();

            $action = match (true) {
                $attendance === null || $attendance->check_in_at === null => 'check-in',
                $attendance->check_out_at === null => 'check-out',
                default => 'done',
            };

            if ($action === 'done') {
                return response()->json([
                    'action' => 'done',
                    'message' => 'سجّلت حضورك وانصرافك لليوم.',
                    'check_in_at' => $attendance->check_in_at?->toIso8601String(),
                    'check_out_at' => $attendance->check_out_at?->toIso8601String(),
                ], 409);
            }

            $result = $action === 'check-in'
                ? $this->attendanceService->checkIn($employee, $device, $request->latitude(), $request->longitude(), $request->ip() ?? '0.0.0.0')
                : $this->attendanceService->checkOut($employee, $device, $request->latitude(), $request->longitude(), $request->ip() ?? '0.0.0.0');

            return response()->json([
                'action' => $action,
                'attendance' => (new AttendanceResource($result))->resolve(),
                'message' => $action === 'check-in' ? 'تم تسجيل حضورك' : 'تم تسجيل انصرافك',
            ]);
        } catch (AttendanceModuleException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->statusCode());
        }
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
     * asking the employee to guess.
     */
    public function status(ScanStatusRequest $request): JsonResponse
    {
        try {
            $device = $this->qrTokens->resolveDevice($request->qrToken());
            $employee = $this->resolveEmployee($request);

            // Half-open range on `date` (not a bare equality) — same reason
            // as record() and AttendanceService::checkIn: SQLite stores the
            // `date` cast as "YYYY-MM-DD 00:00:00", so a bare string match
            // would miss every row and the kiosk would mis-render state.
            $today = now()->startOfDay();
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('date', '>=', $today)
                ->where('date', '<', $today->copy()->addDay())
                ->first();

            $state = match (true) {
                $attendance === null || $attendance->check_in_at === null => 'not_checked_in',
                $attendance->check_out_at === null => 'checked_in',
                default => 'checked_out',
            };

            // full_name is deliberately NOT returned on PIN-only mode: the
            // owner's call is "no name, no confirmation screen" (see memory
            // project-pin-only-scan-decision). Still returned when PIN mode
            // is off because the legacy employee_number-only flow needs it
            // for its confirmation screen; the number-only flow IS the
            // directory-enumeration risk that PIN-only closes down.
            return response()->json([
                'data' => [
                    'state' => $state,
                    'employee' => [
                        'id' => $employee->id,
                        'employee_number' => $employee->employee_number,
                        ...($this->scanPins->isRequired() ? [] : ['full_name' => $employee->full_name]),
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
            // Lets the kiosk keep its PIN-less layout until an admin
            // switches enforcement on.
            'pin_required' => $this->scanPins->isRequired(),
        ]);
    }

    /**
     * Shared plumbing for check-in/check-out: resolve the device from the
     * QR token, resolve the employee, run the given action, and translate
     * any module exception into its HTTP response.
     *
     * @param  \Closure(Employee, AttendanceDevice): Attendance  $action
     */
    private function handleScan(ScanRequest $request, \Closure $action): JsonResponse
    {
        try {
            $device = $this->qrTokens->resolveDevice($request->qrToken());
            $employee = $this->resolveEmployee($request);

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

    /**
     * Runs after the QR token is resolved so a caller without a valid kiosk
     * QR can never burn an employee's PIN attempts.
     */
    private function resolveEmployee(ScanIdentityRequest $request): Employee
    {
        return $this->scanIdentity->resolve(
            $request->bearerToken(),
            $request->employeeNumber(),
            $request->pin(),
            $request->ip() ?? '0.0.0.0',
        );
    }
}
