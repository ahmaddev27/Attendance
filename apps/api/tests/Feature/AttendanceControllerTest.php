<?php

use App\Models\Attendance;
use App\Models\AttendanceDevice;

test('attendance index requires authentication', function () {
    $this->getJson('/api/attendance')->assertUnauthorized();
});

test('an authenticated user can list attendance records', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();
    Attendance::factory()->count(2)->create(['employee_id' => $employee->id]);

    $this->getJson('/api/attendance')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('an authenticated user can filter attendance by employee', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();
    $otherEmployee = makeEmployeeWithSchedule();
    Attendance::factory()->create(['employee_id' => $employee->id]);
    Attendance::factory()->create(['employee_id' => $otherEmployee->id]);

    $response = $this->getJson("/api/attendance?employee_id={$employee->id}");

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('an authenticated user can view a single attendance record', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();
    $attendance = Attendance::factory()->create(['employee_id' => $employee->id]);

    $this->getJson("/api/attendance/{$attendance->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $attendance->id);
});

test('monthly summary endpoint returns the expected structure', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();

    $response = $this->getJson("/api/attendance/employee/{$employee->id}/monthly/2026/1");

    $response->assertOk()->assertJsonStructure([
        'year', 'month', 'total_working_days', 'present_days', 'absent_days',
        'leave_days', 'holiday_days', 'weekend_days', 'total_minutes',
        'total_hours', 'expected_minutes', 'difference_minutes',
        'overtime_minutes', 'late_minutes', 'early_leave_minutes',
        'attendance_percentage',
    ]);
});

test('the scan device-info endpoint reports the device name and expiry countdown', function () {
    $device = AttendanceDevice::factory()->create([
        'qr_rotates_every_seconds' => 300,
        'last_token_rotated_at' => now(),
    ]);

    $response = $this->getJson("/api/scan/device/{$device->qr_token}");

    $response->assertOk()
        ->assertJsonPath('device_name', $device->name)
        ->assertJsonStructure(['device_name', 'server_time', 'token_expires_in']);
});

test('the scan device-info endpoint 404s for an unknown token', function () {
    $this->getJson('/api/scan/device/'.str_repeat('z', 64))->assertNotFound();
});
