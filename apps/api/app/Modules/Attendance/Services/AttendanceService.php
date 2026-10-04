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
            // Half-open range on `date` rather than a bare equality: SQLite
            // (used by the test suite) stores the Laravel `date` cast as
            // "YYYY-MM-DD 00:00:00", so `where('date', '2026-10-01')` yields
            // zero rows and the guard silently misfires. The composite
            // UNIQUE(employee_id, date) index still covers this range.
            $openAttendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('date', '>=', $today->copy()->startOfDay())
                ->where('date', '<', $today->copy()->startOfDay()->addDay())
                ->whereNotNull('check_in_at')
                ->whereNull('check_out_at')
                ->lockForUpdate()
                ->first();

            if ($openAttendance) {
                throw new AttendanceException('You already checked in and have not checked out yet.');
            }

            // Half-open range for the same reason as above: SQLite stores
            // the `date` cast as a full datetime, so a bare equality on
            // the ISO date string matches nothing in the test harness.
            // Range comparison on the indexed column still uses the
            // UNIQUE(employee_id, date) index — DATE() wrappers would not.
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('date', '>=', $today->copy()->startOfDay())
                ->where('date', '<', $today->copy()->startOfDay()->addDay())
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

            // Stamp late_minutes + Late status right now — the previous
            // code deferred every calculation to check-out, so a 3pm
            // arrival against an 8am shift showed as "present, 0 late"
            // in the admin table until the employee eventually scanned
            // out. Compute on check-in so the row reflects reality
            // immediately. Skip silently for employees without a
            // schedule (already asserted upstream in production paths).
            if ($schedule = $employee->workSchedule) {
                $this->calculator->stampCheckInStatus($attendance, $schedule);
            }

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

            // Belt-and-braces guard against "yesterday's forgotten
            // check-in gets stamped with today's time": if the open
            // session is >12h old AND falls on an earlier calendar day,
            // refuse the auto-close and force an admin correction so
            // the historical timesheet reflects reality. The outer
            // query already excludes sessions older than 18h, so this
            // catches the awkward 12-18h cross-midnight window.
            if (
                $attendance->check_in_at !== null
                && $attendance->check_in_at->diffInHours(now()) > 12
                && $attendance->check_in_at->toDateString() !== now()->toDateString()
            ) {
                throw new AttendanceException('لا يمكن تسجيل خروج عن يوم سابق تلقائياً — الرجاء التواصل مع الإدارة لتصحيح البصمة.');
            }

            // Legitimate use case: employee scans in at one entrance and
            // out at another, so we don't reject cross-device check-outs.
            // We do append an anomaly note so ops can audit if a scanning
            // pattern later looks off (e.g. a coworker punching someone
            // else out from a shared kiosk).
            if (
                $attendance->check_in_device_id !== null
                && (int) $attendance->check_in_device_id !== (int) $device->id
            ) {
                $attendance->notes = trim(
                    ($attendance->notes ?? '')."\n[نظام] تسجيل الخروج من جهاز مختلف عن جهاز الحضور."
                );
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

    /**
     * Admin manual correction — edit check_in_at / check_out_at / status /
     * notes on an already-recorded row, then re-derive the computed hours
     * so the admin table and payroll both see consistent numbers.
     *
     * Owner's call 2026-10-04 after seeing bad clock-outs on the live
     * admin page: an admin needs to fix typos the kiosk cannot undo.
     * The route stays behind `view-all-attendance`; the service does the
     * recompute itself so the controller stays thin.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateByAdmin(Attendance $attendance, array $data): Attendance
    {
        return DB::transaction(function () use ($attendance, $data) {
            $locked = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);

            // Only fill the fields the caller actually sent — `fill` on an
            // associative array with explicit nulls still writes nulls,
            // which is how an admin clears a bad check-out back to "open".
            $locked->fill(array_intersect_key($data, array_flip([
                'check_in_at', 'check_out_at', 'status', 'notes',
            ])))->save();

            // Recompute late / early-leave / total minutes if we touched a
            // timestamp — the derived columns on the row are what the
            // dashboard reads, so a stale "0 late" after a corrected
            // check_in_at would quietly lie on the next render.
            if (
                array_key_exists('check_in_at', $data)
                || array_key_exists('check_out_at', $data)
            ) {
                $schedule = $locked->employee?->workSchedule;
                if ($schedule) {
                    if ($locked->check_out_at !== null) {
                        $this->calculator->computeForAttendance($locked, $schedule);
                    } else {
                        $this->calculator->stampCheckInStatus($locked, $schedule);
                    }
                }
            }

            return $locked->fresh(['employee', 'checkInDevice', 'checkOutDevice']);
        });
    }

    /**
     * Admin delete — removes the attendance row entirely. The companion
     * action for `updateByAdmin`: a corrupt scan (e.g. kiosk fired twice
     * on a flaky network) is sometimes cleaner to drop than to patch.
     * Not soft-delete: the `attendances` table has no deleted_at column
     * and payroll should never see tombstoned rows anyway.
     */
    public function deleteByAdmin(Attendance $attendance): void
    {
        DB::transaction(fn () => $attendance->delete());
    }

    /**
     * Admin "clear check-out" — reopens the day by nulling the check-out
     * columns + derived minutes. Separate from updateByAdmin so the UI
     * can wire a one-click "undo clock-out" button without the admin
     * having to type a payload.
     */
    public function clearCheckOutByAdmin(Attendance $attendance): Attendance
    {
        return DB::transaction(function () use ($attendance) {
            $locked = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);

            $locked->fill([
                'check_out_at' => null,
                'check_out_ip' => null,
                'check_out_lat' => null,
                'check_out_lng' => null,
                'check_out_device_id' => null,
                'total_minutes' => null,
                'early_leave_minutes' => null,
                'overtime_minutes' => null,
            ])->save();

            // Re-run the check-in-only stamp so late_minutes stays in
            // sync with the schedule's grace window.
            if ($schedule = $locked->employee?->workSchedule) {
                $this->calculator->stampCheckInStatus($locked, $schedule);
            }

            return $locked->fresh(['employee', 'checkInDevice', 'checkOutDevice']);
        });
    }
}
