<?php

declare(strict_types=1);

use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Admins cannot read the server log, so the settings page shows the latest
 * SMS attempts with the carrier's answer: without it a welcome SMS that
 * never arrived was impossible to diagnose.
 */
function actingAsSettingsManager(): User
{
    Permission::findOrCreate('manage-settings', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('manage-settings');
    Sanctum::actingAs($user);

    return $user;
}

test('the latest sms attempts are listed newest first with masked numbers and the carrier answer', function () {
    actingAsSettingsManager();

    $older = SmsLog::query()->create(['to' => '970599111222', 'body' => 'a', 'status' => 'sent', 'provider_message_id' => 'm-1']);
    $older->forceFill(['created_at' => Carbon::parse('2026-09-15 08:00')])->save();

    $newer = SmsLog::query()->create([
        'to' => '970599123456',
        'body' => 'مرحباً [REDACTED]',
        'status' => 'failed',
        'error' => 'provider_10003',
        'raw_response' => ['status' => 200, 'body' => '10003@ONE OR MORE FIELDS IS EMPTY'],
    ]);
    $newer->forceFill(['created_at' => Carbon::parse('2026-09-15 09:00')])->save();

    $this->getJson('/api/admin/sms-logs')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.to', '970******456')
        ->assertJsonPath('data.0.status', 'failed')
        ->assertJsonPath('data.0.error', 'provider_10003')
        ->assertJsonPath('data.0.provider_response', '10003@ONE OR MORE FIELDS IS EMPTY')
        ->assertJsonPath('data.1.provider_message_id', 'm-1')
        ->assertJsonMissingPath('data.0.body');
});

test('the list size is capped', function () {
    actingAsSettingsManager();

    $this->getJson('/api/admin/sms-logs?limit=500')->assertUnprocessable();
    $this->getJson('/api/admin/sms-logs?limit=5')->assertOk();
});

test('sms logs need manage-settings', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/admin/sms-logs')->assertForbidden();
});
