<?php

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Shared\Enums\AttendanceStatus;

test('an employee can check in via a valid scan', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->create();

    $response = $this->postJson('/api/scan/check-in', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.employee_id', $employee->id)
        ->assertJsonPath('data.status', AttendanceStatus::Present->value)
        ->assertJsonPath('data.check_out_at', null);

    expect(Attendance::query()->where('employee_id', $employee->id)->first())
        ->not->toBeNull()
        ->check_in_at->not->toBeNull();
});

test('check-in rejects an unknown employee number', function () {
    $device = AttendanceDevice::factory()->create();

    $response = $this->postJson('/api/scan/check-in', [
        'employee_number' => 999999,
        'qr_token' => $device->qr_token,
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Unknown employee number.');
});

test('check-in rejects an unknown qr token', function () {
    $employee = makeEmployeeWithSchedule();

    $response = $this->postJson('/api/scan/check-in', [
        'employee_number' => $employee->employee_number,
        'qr_token' => str_repeat('x', 64),
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid or unknown QR token.');
});

test('check-in rejects an expired qr token', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->create([
        'qr_rotates_every_seconds' => 60,
        'last_token_rotated_at' => now()->subMinutes(5),
    ]);

    $response = $this->postJson('/api/scan/check-in', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'This QR code has expired. Please rescan.');
});

test('an employee cannot check in twice without checking out', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->create();

    $payload = [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ];

    $this->postJson('/api/scan/check-in', $payload)->assertOk();
    $response = $this->postJson('/api/scan/check-in', $payload);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'You already checked in and have not checked out yet.');

    expect(Attendance::query()->where('employee_id', $employee->id)->count())->toBe(1);
});

test('check-in passes the geofence when the scan is within the allowed radius', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->withGeofence(31.9539, 35.9106, 200)->create();

    $response = $this->postJson('/api/scan/check-in', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
        // ~30 meters from the device's allowed point.
        'latitude' => 31.95415,
        'longitude' => 35.91065,
    ]);

    $response->assertOk();
});

test('check-in rejects a scan outside the allowed geofence radius', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->withGeofence(31.9539, 35.9106, 100)->create();

    $response = $this->postJson('/api/scan/check-in', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
        // Roughly 11km away — well outside a 100m geofence.
        'latitude' => 32.0500,
        'longitude' => 35.9106,
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('message', 'You are outside the allowed check-in area for this device.');
});

test('check-in rejects a request from an ip outside the device whitelist', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->withIpWhitelist(['203.0.113.5'])->create();

    // The test client's default request IP (127.0.0.1) is not in the list.
    $response = $this->postJson('/api/scan/check-in', [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('message', 'Your network is not permitted to check in on this device.');
});
