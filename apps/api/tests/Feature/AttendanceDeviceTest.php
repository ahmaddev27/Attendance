<?php

use App\Models\AttendanceDevice;

test('attendance devices index requires authentication', function () {
    $this->getJson('/api/attendance-devices')->assertUnauthorized();
});

test('an authenticated user can list attendance devices', function () {
    actingAsAdmin();
    AttendanceDevice::factory()->count(2)->create();

    $this->getJson('/api/attendance-devices')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('creating an attendance device auto-generates a qr token', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/attendance-devices', [
        'name' => 'Main Office',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Main Office')
        ->assertJsonPath('data.qr_rotates_every_seconds', 300);

    $token = $response->json('data.qr_token');
    expect($token)->toBeString()->and(strlen($token))->toBe(64);
});

test('an authenticated user can view a single attendance device', function () {
    actingAsAdmin();
    $device = AttendanceDevice::factory()->create();

    $this->getJson("/api/attendance-devices/{$device->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $device->id);
});

test('an authenticated user can update an attendance device', function () {
    actingAsAdmin();
    $device = AttendanceDevice::factory()->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/attendance-devices/{$device->id}", ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
});

test('an authenticated user can delete an attendance device', function () {
    actingAsAdmin();
    $device = AttendanceDevice::factory()->create();

    $this->deleteJson("/api/attendance-devices/{$device->id}")->assertNoContent();
    $this->assertDatabaseMissing('attendance_devices', ['id' => $device->id]);
});

test('rotating a device token changes the token and rotation timestamp', function () {
    actingAsAdmin();
    $device = AttendanceDevice::factory()->create([
        'qr_token' => str_repeat('a', 64),
        'last_token_rotated_at' => now()->subDay(),
    ]);
    $originalToken = $device->qr_token;

    $response = $this->postJson("/api/attendance-devices/{$device->id}/rotate");

    $response->assertOk();

    $newToken = $response->json('data.qr_token');
    expect($newToken)->not->toBe($originalToken)
        ->and(strlen($newToken))->toBe(64);

    $device->refresh();
    expect($device->last_token_rotated_at->diffInSeconds(now()))->toBeLessThan(5);
});
