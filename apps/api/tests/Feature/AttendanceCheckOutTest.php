<?php

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Checks in at the given time-of-day "today", returning the employee and
 * device used so the test can check out afterwards.
 */
function checkInAt(string $time): array
{
    $today = Carbon::today();
    Carbon::setTestNow($today->copy()->setTimeFromTimeString($time));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 15,
        'grace_early_leave_minutes' => 15,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);
    // A day-long rotation window: these tests fast-forward the clock to a
    // check-out time hours after check-in, and token expiry is exercised
    // separately in AttendanceCheckInTest — it isn't what's under test here.
    $device = AttendanceDevice::factory()->create(['qr_rotates_every_seconds' => 86400]);

    $response = test()->postJson('/api/scan/check-in', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ]);

    $response->assertOk();

    return [$employee, $device];
}

test('an employee can check out after checking in', function () {
    [$employee, $device] = checkInAt('08:00');

    Carbon::setTestNow(Carbon::today()->setTime(16, 0));

    $response = $this->postJson('/api/scan/check-out', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.employee_id', $employee->id);

    $attendance = Attendance::query()->where('employee_id', $employee->id)->first();
    expect($attendance->check_out_at)->not->toBeNull();
});

test('check-out is rejected without a prior check-in', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->create();

    $response = $this->postJson('/api/scan/check-out', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'You must check in before checking out.');
});

test('check-out rejects a check-out time before the check-in time', function () {
    [$employee, $device] = checkInAt('09:00');

    // Simulate a clock that has gone backwards relative to check-in.
    Carbon::setTestNow(Carbon::today()->setTime(8, 0));

    $response = $this->postJson('/api/scan/check-out', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Check-out time cannot be before check-in time.');
});

test('check-out computes total_minutes from the check-in/check-out gap', function () {
    [$employee, $device] = checkInAt('08:00');

    Carbon::setTestNow(Carbon::today()->setTime(16, 30));

    $this->postJson('/api/scan/check-out', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ])->assertOk();

    $attendance = Attendance::query()->where('employee_id', $employee->id)->first();

    expect($attendance->total_minutes)->toBe(510)
        ->and($attendance->total_hours)->toBe(8.5);
});

test('check-out computes late, early-leave and overtime minutes net of grace periods', function () {
    // 20 minutes late (grace 15) and leaves 20 minutes early (grace 15):
    // both breach their grace window by exactly 5 minutes.
    [$employee, $device] = checkInAt('08:20');

    Carbon::setTestNow(Carbon::today()->setTime(15, 40));

    $this->postJson('/api/scan/check-out', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ])->assertOk();

    $attendance = Attendance::query()->where('employee_id', $employee->id)->first();

    expect($attendance->late_minutes)->toBe(5)
        ->and($attendance->early_leave_minutes)->toBe(5)
        ->and($attendance->overtime_minutes)->toBe(0)
        // Late takes priority over early-leave per the spec's status rule.
        ->and($attendance->status)->toBe(AttendanceStatus::Late);
});
