<?php

use App\Livewire\Admin\Settings\SettingsForm;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;
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

it('never loads the existing sms password into the form or its snapshot', function () {
    $settings = app(SettingsService::class);
    $settings->set('sms_password', 'super-secret-password', 'string');

    Livewire::test(SettingsForm::class)
        ->assertSet('smsPassword', '')
        // stripInitialData: false — inspect the raw wire:snapshot attribute
        // itself (the JSON blob assertSee/assertDontSee strip by default),
        // since that is exactly what a page-source leak would expose.
        ->assertDontSee('super-secret-password', escape: true, stripInitialData: false);
});

it('encrypts the sms password at rest and decrypts it transparently', function () {
    $settings = app(SettingsService::class);
    $settings->set('sms_password', 'super-secret-password', 'string');

    $raw = Setting::where('key', 'sms_password')->value('value');

    expect($raw)->not->toBe('super-secret-password');
    expect(Crypt::decryptString($raw))->toBe('super-secret-password');
    expect($settings->get('sms_password'))->toBe('super-secret-password');
});

it('updates the sms password when a new value is submitted', function () {
    $settings = app(SettingsService::class);
    $settings->set('sms_password', 'old-password', 'string');

    Livewire::test(SettingsForm::class)
        ->set('smsPassword', 'new-password')
        ->call('save');

    expect(app(SettingsService::class)->get('sms_password'))->toBe('new-password');
});

it('keeps the existing sms password when the field is left empty on save', function () {
    $settings = app(SettingsService::class);
    $settings->set('sms_password', 'keep-me', 'string');

    Livewire::test(SettingsForm::class)
        ->set('smsPassword', '')
        ->call('save');

    expect(app(SettingsService::class)->get('sms_password'))->toBe('keep-me');
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
