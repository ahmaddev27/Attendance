<?php

use App\Models\WorkSchedule;

test('work schedules index requires authentication', function () {
    $this->getJson('/api/work-schedules')->assertUnauthorized();
});

test('an authenticated user can list work schedules', function () {
    actingAsAdmin();
    WorkSchedule::factory()->count(2)->create();

    $this->getJson('/api/work-schedules')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('an authenticated user can create a work schedule', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/work-schedules', [
        'name' => 'Ramadan Hours',
        'check_in_time' => '09:00',
        'check_out_time' => '15:00',
        'min_hours_per_day' => 6,
        'workdays' => [0, 1, 2, 3, 4],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Ramadan Hours')
        ->assertJsonPath('data.expected_minutes', 360);
});

test('creating a work schedule validates workdays', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/work-schedules', [
        'name' => 'Invalid',
        'workdays' => [7],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['workdays.0']);
});

test('an authenticated user can update a work schedule', function () {
    actingAsAdmin();
    $schedule = WorkSchedule::factory()->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/work-schedules/{$schedule->id}", ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
});

test('an authenticated user can delete a work schedule', function () {
    actingAsAdmin();
    $schedule = WorkSchedule::factory()->create();

    $this->deleteJson("/api/work-schedules/{$schedule->id}")->assertNoContent();
    $this->assertDatabaseMissing('work_schedules', ['id' => $schedule->id]);
});
