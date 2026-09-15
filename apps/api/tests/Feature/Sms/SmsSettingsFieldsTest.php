<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The owner configures SMS only from the settings page (never .env), so the
 * country code and the Arabic message type must be editable there too.
 */
beforeEach(function () {
    Permission::findOrCreate('manage-settings', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('manage-settings');
    Sanctum::actingAs($user);
});

test('the sms settings expose the default country code and the unicode type', function () {
    $keys = collect($this->getJson('/api/admin/settings')->assertOk()->json('data.sms'))->pluck('key');

    expect($keys)->toContain('default_country_code')
        ->and($keys)->toContain('mtc_unicode_type');
});

test('the default country code must be digits', function () {
    $this->putJson('/api/admin/settings', ['sms' => ['default_country_code' => 'PS']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sms.default_country_code');

    $this->putJson('/api/admin/settings', ['sms' => ['default_country_code' => '+970']])->assertOk();
});

test('the unicode type must be a single digit', function () {
    $this->putJson('/api/admin/settings', ['sms' => ['mtc_unicode_type' => 'arabic']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sms.mtc_unicode_type');

    $this->putJson('/api/admin/settings', ['sms' => ['mtc_unicode_type' => '2']])->assertOk();
});
