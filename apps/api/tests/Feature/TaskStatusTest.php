<?php

use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

test('task statuses index requires authentication', function () {
    $this->getJson('/api/task-statuses')->assertUnauthorized();
});

test('an admin can list task statuses ordered by sort_order', function () {
    $this->actingAsSuperAdmin();
    TaskStatus::factory()->create(['sort_order' => 2, 'name' => 'Second']);
    TaskStatus::factory()->create(['sort_order' => 1, 'name' => 'First']);

    $response = $this->getJson('/api/task-statuses');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.name'))->toBe('First');
});

test('an admin can create a task status', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/task-statuses', [
        'name' => 'قيد التنفيذ',
        'code' => 'in-progress-test',
        'color' => '#2678C4',
        'is_done_state' => false,
    ]);

    $response->assertCreated()->assertJsonPath('data.code', 'in-progress-test');
    $this->assertDatabaseHas('task_statuses', ['code' => 'in-progress-test']);
});

test('creating a task status validates required fields', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/task-statuses', []);

    $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'code']);
});

test('task status code must be unique', function () {
    $this->actingAsSuperAdmin();
    TaskStatus::factory()->create(['code' => 'done-test']);

    $response = $this->postJson('/api/task-statuses', ['name' => 'Another', 'code' => 'done-test']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

test('an admin can view and update a task status', function () {
    $this->actingAsSuperAdmin();
    $status = TaskStatus::factory()->create(['name' => 'Old']);

    $this->getJson("/api/task-statuses/{$status->id}")->assertOk()->assertJsonPath('data.id', $status->id);

    $response = $this->putJson("/api/task-statuses/{$status->id}", ['name' => 'New']);

    $response->assertOk()->assertJsonPath('data.name', 'New');
});

test('an admin can delete an unused task status', function () {
    $this->actingAsSuperAdmin();
    $status = TaskStatus::factory()->create();

    $this->deleteJson("/api/task-statuses/{$status->id}")->assertNoContent();
    $this->assertDatabaseMissing('task_statuses', ['id' => $status->id]);
});

test('deleting a task status assigned to existing tasks is prevented', function () {
    $this->actingAsSuperAdmin();
    $status = TaskStatus::factory()->create();
    Task::factory()->create(['status_id' => $status->id, 'priority_id' => TaskPriority::factory()]);

    $response = $this->deleteJson("/api/task-statuses/{$status->id}");

    $response->assertUnprocessable();
    $this->assertDatabaseHas('task_statuses', ['id' => $status->id]);
});
