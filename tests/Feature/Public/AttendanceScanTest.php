<?php

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Employee;

it('shows the scan landing page', function () {
    $this->get('/scan')->assertOk()->assertSee('تسجيل الحضور');
});

it('shows attendance form', function () {
    $this->get('/scan/attendance')->assertOk();
});

it('rejects unknown employee number', function () {
    $this->post('/scan/attendance/preview', ['employee_number' => 9999])
        ->assertSessionHasErrors('employee_number');
});

it('previews check_in for new employee', function () {
    $emp = Employee::factory()->create(['employee_number' => 1001]);

    $this->post('/scan/attendance/preview', ['employee_number' => 1001])
        ->assertOk()
        ->assertSee('check_in');
});

it('rejects inactive employee', function () {
    Employee::factory()->create(['employee_number' => 1001, 'is_active' => false]);

    $this->post('/scan/attendance/preview', ['employee_number' => 1001])
        ->assertSessionHasErrors('employee_number');
});

it('persists attendance on confirm', function () {
    $emp = Employee::factory()->create(['employee_number' => 1001]);

    $this->post('/scan/attendance/confirm', ['employee_number' => 1001])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Attendance::count())->toBe(1);
    expect(Attendance::first()->type)->toBe(AttendanceType::CheckIn);
});

it('rejects direct confirm POST when fraud check fails', function () {
    $emp = Employee::factory()->create(['employee_number' => 1001]);
    $s = app(\App\Services\SettingsService::class);
    $s->set('gps_enabled', true, 'boolean');
    $s->set('office_lat', 31.9539, 'number');
    $s->set('office_lng', 35.9106, 'number');
    $s->set('geofence_radius_meters', 100, 'number');

    $this->post('/scan/attendance/confirm', [
        'employee_number' => 1001,
        'latitude' => 32.0,
        'longitude' => 36.0,
    ])->assertRedirect(route('scan.index'))
      ->assertSessionHas('toast');

    expect(\App\Models\Attendance::count())->toBe(0);
});
