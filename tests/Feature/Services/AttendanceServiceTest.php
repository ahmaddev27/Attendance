<?php

use App\DataObjects\FraudCheckContext;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceService;

it('records first scan of the day as check_in', function () {
    $emp = Employee::factory()->create();
    $att = app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));

    expect($att->type)->toBe(AttendanceType::CheckIn);
});

it('records second scan as check_out', function () {
    $emp = Employee::factory()->create();
    app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));
    $att = app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));

    expect($att->type)->toBe(AttendanceType::CheckOut);
});

it('third scan same day starts new check_in', function () {
    $emp = Employee::factory()->create();
    app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));
    app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));
    $att = app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));

    expect($att->type)->toBe(AttendanceType::CheckIn);
});

it('previews next scan type without persisting', function () {
    $emp = Employee::factory()->create();
    $type = app(AttendanceService::class)->previewNextType($emp);

    expect($type)->toBe(AttendanceType::CheckIn);
    expect(Attendance::count())->toBe(0);
});

it('throws and does not persist when fraud check fails', function () {
    $emp = Employee::factory()->create();

    $settings = app(\App\Services\SettingsService::class);
    $settings->set('gps_enabled', true, 'boolean');
    $settings->set('office_lat', 31.9539, 'number');
    $settings->set('office_lng', 35.9106, 'number');
    $settings->set('geofence_radius_meters', 100, 'number');

    $ctx = new FraudCheckContext('1.1.1.1', 32.0, 36.0);

    expect(fn () => app(AttendanceService::class)->record($emp, $ctx))
        ->toThrow(\DomainException::class, 'gps_failed');

    expect(Attendance::count())->toBe(0);
});
