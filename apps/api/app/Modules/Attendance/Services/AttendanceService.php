<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Modules\Attendance\Exceptions\AttendanceException;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the scan check-in/check-out flow: fraud checks, state
 * transitions, and (on check-out) delegating to WorkingHoursCalculator.
 *
 * Every mutation runs inside a transaction with `lockForUpdate()` on the
 * row being changed, so two near-simultaneous scans for the same employee
 * (e.g. a double-tap, or a retried request) can't both succeed and create
 * inconsistent state.
 */
class AttendanceService
{
    public function __construct(
        private readonly FraudGuardService $fraudGuard,
        private readonly WorkingHoursCalculator $calculator,
    ) {}

    public function checkIn(
        Employee $employee,
        AttendanceDevice $device,
        ?float $latitude,
        ?float $longitude,
        string $ip,
    ): Attendance {
        $this->fraudGuard->assertAllowed($device, $latitude, $longitude, $ip);

        return DB::transaction(function () use ($employee, $device, $latitude, $longitude, $ip) {
            $today = Carbon::today();

            // Scoped to TODAY only — an unclosed session from weeks ago
            // (employee who forgot to check out) must never permanently
            // block a new check-in. Anything older is treated as orphaned
            // and left for the admin correction flow; today's open row
            // still blocks so a double-tap can't create two sessions.
            $openAttendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today->toDateString())
                ->whereNotNull('check_in_at')
                ->whereNull('check_out_at')
                ->lockForUpdate()
                ->first();

            if ($openAttendance) {
                throw new AttendanceException('You already checked in and have not checked out yet.');
            }

            // Bare where() on the DATE column — keeps the
            // UNIQUE(employee_id, date) index in play (DATE() wrappers
            // would disqualify it and force a full-table scan).
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today->toDateString())
                ->lockForUpdate()
                ->first();

            if ($attendance && $attendance->check_out_at) {
                throw new AttendanceException('You have already completed attendance for today.');
            }

            $attendance ??= new Attendance([
                'employee_id' => $employee->id,
                'date' => $today,
            ]);

            $attendance->fill([
                'check_in_at' => now(),
                'check_in_ip' => $ip,
                'check_in_lat' => $latitude,
                'check_in_lng' => $longitude,
                'check_in_device_id' => $device->id,
                'status' => AttendanceStatus::Present,
            ])->save();

            // Load employee — the kiosk `AttendanceResource` needs it for
            // the success card (name + number). `whenLoaded` in the resource
            // hides the key otherwise, and the FE crashes on `.employee`.
            return $attendance->fresh(['employee']);
        });
    }

    public function checkOut(
        Employee $employee,
        AttendanceDevice $device,
        ?float $latitude,
        ?float $longitude,
        string $ip,
    ): Attendance {
        $this->fraudGuard->assertAllowed($device, $latitude, $longitude, $ip);

        return DB::transaction(function () use ($employee, $device, $latitude, $longitude, $ip) {
            // Matched by "open session" rather than "today's row" so a shift
            // that started before midnight can still be closed afterwards.
            // A stale session older than 18 hours is treated as orphaned:
            // refuse the check-out and force an admin correction, so an
            // employee who forgot to check out days ago can't retroactively
            // stamp a weeks-old session with today's time.
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereNotNull('check_in_at')
                ->whereNull('check_out_at')
                ->where('check_in_at', '>=', now()->subHours(18))
                ->lockForUpdate()
                ->latest('date')
                ->first();

            if (! $attendance) {
                // If there IS a stale open session, surface a distinct
                // message so ops know to intervene (auto-closing here would
                // silently rewrite historical timesheets — not our call).
                $hasStale = Attendance::query()
                    ->where('employee_id', $employee->id)
                    ->whereNotNull('check_in_at')
                    ->whereNull('check_out_at')
                    ->exists();

                if ($hasStale) {
                    throw new AttendanceException('You have an unclosed session older than 18 hours. Please ask an administrator to correct it before checking out again.');
                }

                throw new AttendanceException('You must check in before checking out.');
            }

            $checkOutAt = now();

            if ($checkOutAt->lessThan($attendance->check_in_at)) {
                throw new AttendanceException('Check-out time cannot be before check-in time.');
            }

            $attendance->fill([
                'check_out_at' => $checkOutAt,
                'check_out_ip' => $ip,
                'check_out_lat' => $latitude,
                'check_out_lng' => $longitude,
                'check_out_device_id' => $device->id,
            ])->save();

            if ($schedule = $employee->workSchedule) {
                $this->calculator->computeForAttendance($attendance, $schedule);
            }

            // Load employee — the kiosk `AttendanceResource` needs it for
            // the success card (name + number). `whenLoaded` in the resource
            // hides the key otherwise, and the FE crashes on `.employee`.
            return $attendance->fresh(['employee']);
        });
    }
}
