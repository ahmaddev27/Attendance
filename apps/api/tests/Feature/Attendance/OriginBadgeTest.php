<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\AttendanceDevice;

/**
 * Builds an attendance row tied to a device with the given whitelist and
 * returns the id so each test can hit /api/attendance/{id} and assert on
 * the computed `origin` field. The resource reads check_in_ip +
 * check_in_device.ip_whitelist, so both must land on the row.
 */
function makeAttendanceWithOrigin(?array $whitelist, ?string $ip, bool $attachDevice = true): Attendance
{
    $employee = makeEmployeeWithSchedule();

    $deviceId = null;
    if ($attachDevice) {
        $device = AttendanceDevice::factory()->create([
            'ip_whitelist' => $whitelist,
        ]);
        $deviceId = $device->id;
    }

    return Attendance::factory()->create([
        'employee_id' => $employee->id,
        'check_in_ip' => $ip,
        'check_in_device_id' => $deviceId,
    ]);
}

test('origin is onsite when the check-in IP matches a whitelist entry', function () {
    actingAsAdmin();
    $attendance = makeAttendanceWithOrigin(['10.0.0.5'], '10.0.0.5');

    $this->getJson("/api/attendance/{$attendance->id}")
        ->assertOk()
        ->assertJsonPath('data.origin', 'onsite');
});

test('origin is remote when the check-in IP falls outside a non-empty whitelist', function () {
    actingAsAdmin();
    $attendance = makeAttendanceWithOrigin(['10.0.0.0/24'], '203.0.113.4');

    $this->getJson("/api/attendance/{$attendance->id}")
        ->assertOk()
        ->assertJsonPath('data.origin', 'remote');
});

test('origin is unknown when the device has an empty whitelist', function () {
    actingAsAdmin();
    $attendance = makeAttendanceWithOrigin([], '10.0.0.5');

    $this->getJson("/api/attendance/{$attendance->id}")
        ->assertOk()
        ->assertJsonPath('data.origin', 'unknown');
});

test('origin is unknown when no device is attached to the check-in', function () {
    actingAsAdmin();
    $attendance = makeAttendanceWithOrigin(null, '10.0.0.5', attachDevice: false);

    $this->getJson("/api/attendance/{$attendance->id}")
        ->assertOk()
        ->assertJsonPath('data.origin', 'unknown');
});

test('origin matches an IPv6 whitelist entry exactly', function () {
    actingAsAdmin();
    $attendance = makeAttendanceWithOrigin(['2001:db8::1'], '2001:db8::1');

    $this->getJson("/api/attendance/{$attendance->id}")
        ->assertOk()
        ->assertJsonPath('data.origin', 'onsite');
});

test('origin honours an IPv4 CIDR range', function () {
    actingAsAdmin();
    $inside = makeAttendanceWithOrigin(['192.168.1.0/24'], '192.168.1.42');
    $outside = makeAttendanceWithOrigin(['192.168.1.0/24'], '192.168.2.42');

    $this->getJson("/api/attendance/{$inside->id}")
        ->assertOk()
        ->assertJsonPath('data.origin', 'onsite');

    $this->getJson("/api/attendance/{$outside->id}")
        ->assertOk()
        ->assertJsonPath('data.origin', 'remote');
});

test('origin honours an IPv6 CIDR range', function () {
    actingAsAdmin();
    $inside = makeAttendanceWithOrigin(['2001:db8:abcd::/48'], '2001:db8:abcd::1234');

    $this->getJson("/api/attendance/{$inside->id}")
        ->assertOk()
        ->assertJsonPath('data.origin', 'onsite');
});
