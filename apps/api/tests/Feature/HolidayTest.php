<?php

use App\Models\Holiday;
use App\Shared\Enums\HolidayType;

test('holidays index requires authentication', function () {
    $this->getJson('/api/holidays')->assertUnauthorized();
});

test('an authenticated user can list holidays', function () {
    actingAsAdmin();
    Holiday::factory()->count(3)->create();

    $response = $this->getJson('/api/holidays');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('an authenticated user can create a holiday', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/holidays', [
        'date' => '2026-05-01',
        'name' => 'Labor Day',
        'type' => HolidayType::Official->value,
        'is_recurring' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Labor Day')
        ->assertJsonPath('data.date', '2026-05-01')
        ->assertJsonPath('data.is_recurring', true);

    // Not assertDatabaseHas(['date' => '2026-05-01', ...]): the `date`
    // cast persists as a full 'Y-m-d H:i:s' string, so a bare date-only
    // value would never match the stored row.
    expect(Holiday::query()->where('name', 'Labor Day')->first()?->date->toDateString())
        ->toBe('2026-05-01');
});

test('creating a holiday validates required fields', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/holidays', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['date', 'name', 'type']);
});

test('an authenticated user can view a single holiday', function () {
    actingAsAdmin();
    $holiday = Holiday::factory()->create();

    $response = $this->getJson("/api/holidays/{$holiday->id}");

    $response->assertOk()->assertJsonPath('data.id', $holiday->id);
});

test('an authenticated user can update a holiday', function () {
    actingAsAdmin();
    $holiday = Holiday::factory()->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/holidays/{$holiday->id}", ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
    $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'name' => 'New Name']);
});

test('an authenticated user can delete a holiday', function () {
    actingAsAdmin();
    $holiday = Holiday::factory()->create();

    $response = $this->deleteJson("/api/holidays/{$holiday->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
});
