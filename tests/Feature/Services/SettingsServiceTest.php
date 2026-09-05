<?php

use App\Services\SettingsService;
use App\Models\Setting;

beforeEach(fn () => cache()->flush());

it('returns default when setting is missing', function () {
    $service = app(SettingsService::class);
    expect($service->get('missing_key', 'default'))->toBe('default');
});

it('reads a string setting', function () {
    Setting::create(['key' => 'sms_username', 'value' => 'acme', 'type' => 'string']);
    $service = app(SettingsService::class);
    expect($service->get('sms_username'))->toBe('acme');
});

it('casts boolean settings', function () {
    Setting::create(['key' => 'gps_enabled', 'value' => '1', 'type' => 'boolean']);
    $service = app(SettingsService::class);
    expect($service->get('gps_enabled'))->toBeTrue();
});

it('casts number settings', function () {
    Setting::create(['key' => 'geofence_radius_meters', 'value' => '100', 'type' => 'number']);
    $service = app(SettingsService::class);
    expect($service->get('geofence_radius_meters'))->toBe(100);
});

it('casts json settings', function () {
    Setting::create(['key' => 'ip_whitelist', 'value' => json_encode(['1.1.1.1']), 'type' => 'json']);
    $service = app(SettingsService::class);
    expect($service->get('ip_whitelist'))->toBe(['1.1.1.1']);
});

it('sets a setting and invalidates cache', function () {
    $service = app(SettingsService::class);
    $service->set('foo', 'bar', 'string');
    expect($service->get('foo'))->toBe('bar');
});
