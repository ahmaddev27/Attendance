<?php

use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

test('task priorities index requires authentication', function () {
    $this->getJson('/api/task-priorities')->assertUnauthorized();
});

test('an admin can list task priorities ordered by sort_order', function () {
    $this->actingAsSuperAdmin();
    TaskPriority::factory()->create(['sort_order' => 2, 'name' => 'Second']);
    TaskPriority::factory()->create(['sort_order' => 1, 'name' => 'First']);

    $response = $this->getJson('/api/task-priorities');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.name'))->toBe('First');
});

test('an admin can create a task priority', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/task-priorities', [
        'name' => 'عاجل',
        'code' => 'urgent-test',
        'color' => '#C74F35',
    ]);

    $response->assertCreated()->assertJsonPath('data.code', 'urgent-test');
    $this->assertDatabaseHas('task_priorities', ['code' => 'urgent-test']);
});

test('creating a task priority validates required fields', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/task-priorities', []);

    $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'code', 'color']);
});

test('task priority code must be unique', function () {
    $this->actingAsSuperAdmin();
    TaskPriority::factory()->create(['code' => 'high-test']);

    $response = $this->postJson('/api/task-priorities', ['name' => 'Another', 'code' => 'high-test', 'color' => '#000000']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

test('an admin can view and update a task priority', function () {
    $this->actingAsSuperAdmin();
    $priority = TaskPriority::factory()->create(['name' => 'Old']);

    $this->getJson("/api/task-priorities/{$priority->id}")->assertOk()->assertJsonPath('data.id', $priority->id);

    $response = $this->putJson("/api/task-priorities/{$priority->id}", ['name' => 'New']);

    $response->assertOk()->assertJsonPath('data.name', 'New');
});

test('an admin can delete an unused task priority', function () {
    $this->actingAsSuperAdmin();
    $priority = TaskPriority::factory()->create();

    $this->deleteJson("/api/task-priorities/{$priority->id}")->assertNoContent();
    $this->assertDatabaseMissing('task_priorities', ['id' => $priority->id]);
});

test('deleting a task priority assigned to existing tasks is prevented', function () {
    $this->actingAsSuperAdmin();
    $priority = TaskPriority::factory()->create();
    Task::factory()->create(['priority_id' => $priority->id, 'status_id' => TaskStatus::factory()]);

    $response = $this->deleteJson("/api/task-priorities/{$priority->id}");

    $response->assertUnprocessable();
    $this->assertDatabaseHas('task_priorities', ['id' => $priority->id]);
});
