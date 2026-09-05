<?php

use App\Livewire\Admin\Settings\SettingsForm;
use App\Models\User;
use App\Services\SettingsService;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(\Database\Seeders\SettingsSeeder::class);
    cache()->flush();
});

it('loads current settings into the form', function () {
    Livewire::test(SettingsForm::class)
        ->assertSet('gpsEnabled', false)
        ->assertSet('geofenceRadius', 100);
});

it('saves settings back', function () {
    Livewire::test(SettingsForm::class)
        ->set('gpsEnabled', true)
        ->set('officeLat', 31.9539)
        ->set('officeLng', 35.9106)
        ->set('geofenceRadius', 150)
        ->set('ipEnabled', true)
        ->set('ipWhitelistText', "192.168.1.100\n10.0.0.5")
        ->call('save');

    $settings = app(SettingsService::class);
    expect($settings->get('gps_enabled'))->toBeTrue();
    expect($settings->get('geofence_radius_meters'))->toBe(150);
    expect($settings->get('ip_whitelist'))->toBe(['192.168.1.100', '10.0.0.5']);
});
