<?php

use App\Models\AttendanceDevice;
use App\Models\Employee;

/**
 * Laravel keys every unnamed `throttle:N,M` middleware by the same user-or-IP
 * signature. Without a bucket per route, the kiosk's device polling and the
 * status probes spend the check-in budget of everyone behind the same office
 * IP, so a morning rush on office Wi-Fi turns into 429s at the door.
 *
 * @return array{employee_number: int, qr_token: string}
 */
function scanThrottlePayload(Employee $employee, AttendanceDevice $device): array
{
    return [
        'employee_number' => (int) $employee->employee_number,
        'qr_token' => $device->qr_token,
    ];
}

test('kiosk device polling does not use up the check-in limit of the same IP', function () {
    $employee = makeEmployeeWithSchedule(makeWorkSchedule(['is_flexible' => true]));
    $device = AttendanceDevice::factory()->create();

    for ($poll = 0; $poll < 30; $poll++) {
        $this->getJson("/api/scan/device/{$device->qr_token}")->assertOk();
    }

    $this->postJson('/api/scan/check-in', scanThrottlePayload($employee, $device))->assertOk();
});

test('status probes do not use up the check-in limit of the same IP', function () {
    $employee = makeEmployeeWithSchedule(makeWorkSchedule(['is_flexible' => true]));
    $device = AttendanceDevice::factory()->create();

    for ($probe = 0; $probe < 30; $probe++) {
        $this->postJson('/api/scan/status', scanThrottlePayload($employee, $device))->assertSuccessful();
    }

    $this->postJson('/api/scan/check-in', scanThrottlePayload($employee, $device))->assertOk();
});

test('check-in still enforces its own limit of 30 per minute per IP', function () {
    $device = AttendanceDevice::factory()->create();
    $unknown = ['employee_number' => 999999, 'qr_token' => $device->qr_token];

    for ($attempt = 0; $attempt < 30; $attempt++) {
        $this->postJson('/api/scan/check-in', $unknown)->assertUnauthorized();
    }

    $this->postJson('/api/scan/check-in', $unknown)->assertTooManyRequests();
});
