<?php

use App\DataObjects\FraudCheckContext;
use App\Enums\FraudCheckStatus;
use App\Services\FraudGuardService;
use App\Services\SettingsService;

function ctx(string $ip = '1.2.3.4', ?float $lat = null, ?float $lng = null): FraudCheckContext {
    return new FraudCheckContext($ip, $lat, $lng);
}

beforeEach(function () {
    $this->settings = app(SettingsService::class);
    $this->settings->set('gps_enabled', false, 'boolean');
    $this->settings->set('ip_enabled', false, 'boolean');
});

it('passes as skipped when both checks disabled', function () {
    $result = app(FraudGuardService::class)->check(ctx());
    expect($result->passed)->toBeTrue();
    expect($result->status)->toBe(FraudCheckStatus::Skipped);
});

it('passes when GPS is within radius', function () {
    $this->settings->set('gps_enabled', true, 'boolean');
    $this->settings->set('office_lat', 31.9539, 'number');
    $this->settings->set('office_lng', 35.9106, 'number');
    $this->settings->set('geofence_radius_meters', 100, 'number');

    $result = app(FraudGuardService::class)->check(ctx('1.1.1.1', 31.9539, 35.9106));
    expect($result->passed)->toBeTrue();
});

it('fails when GPS is outside radius', function () {
    $this->settings->set('gps_enabled', true, 'boolean');
    $this->settings->set('office_lat', 31.9539, 'number');
    $this->settings->set('office_lng', 35.9106, 'number');
    $this->settings->set('geofence_radius_meters', 100, 'number');

    $result = app(FraudGuardService::class)->check(ctx('1.1.1.1', 31.9700, 35.9300));
    expect($result->passed)->toBeFalse();
    expect($result->status)->toBe(FraudCheckStatus::GpsFailed);
});

it('passes when IP is whitelisted', function () {
    $this->settings->set('ip_enabled', true, 'boolean');
    $this->settings->set('ip_whitelist', ['192.168.1.100'], 'json');

    $result = app(FraudGuardService::class)->check(ctx('192.168.1.100'));
    expect($result->passed)->toBeTrue();
});

it('fails when IP not whitelisted', function () {
    $this->settings->set('ip_enabled', true, 'boolean');
    $this->settings->set('ip_whitelist', ['192.168.1.100'], 'json');

    $result = app(FraudGuardService::class)->check(ctx('10.0.0.5'));
    expect($result->passed)->toBeFalse();
    expect($result->status)->toBe(FraudCheckStatus::IpFailed);
});

it('passes when either GPS or IP passes with both enabled', function () {
    $this->settings->set('gps_enabled', true, 'boolean');
    $this->settings->set('office_lat', 31.9539, 'number');
    $this->settings->set('office_lng', 35.9106, 'number');
    $this->settings->set('geofence_radius_meters', 100, 'number');
    $this->settings->set('ip_enabled', true, 'boolean');
    $this->settings->set('ip_whitelist', ['192.168.1.100'], 'json');

    // GPS passes, IP fails => overall pass
    $result = app(FraudGuardService::class)->check(ctx('10.0.0.5', 31.9539, 35.9106));
    expect($result->passed)->toBeTrue();

    // IP passes, GPS fails => overall pass
    $result = app(FraudGuardService::class)->check(ctx('192.168.1.100', 31.9700, 35.9300));
    expect($result->passed)->toBeTrue();
});
