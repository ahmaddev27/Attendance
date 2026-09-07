<?php

use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskTag;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

test('task tags index requires authentication', function () {
    $this->getJson('/api/task-tags')->assertUnauthorized();
});

test('an admin can list task tags for autocomplete', function () {
    $this->actingAsSuperAdmin();
    TaskTag::factory()->count(3)->create();

    $response = $this->getJson('/api/task-tags');

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('an admin can create a task tag', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/task-tags', ['name' => 'تطوير-test', 'color' => '#2678C4']);

    $response->assertCreated()->assertJsonPath('data.name', 'تطوير-test');
    $this->assertDatabaseHas('task_tags', ['name' => 'تطوير-test']);
});

test('creating a task tag validates required fields', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/task-tags', []);

    $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
});

test('task tag name must be unique', function () {
    $this->actingAsSuperAdmin();
    TaskTag::factory()->create(['name' => 'اختبار-test']);

    $response = $this->postJson('/api/task-tags', ['name' => 'اختبار-test']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
});

test('an admin can view and update a task tag', function () {
    $this->actingAsSuperAdmin();
    $tag = TaskTag::factory()->create(['name' => 'Old']);

    $this->getJson("/api/task-tags/{$tag->id}")->assertOk()->assertJsonPath('data.id', $tag->id);

    $response = $this->putJson("/api/task-tags/{$tag->id}", ['name' => 'New']);

    $response->assertOk()->assertJsonPath('data.name', 'New');
});

test('an admin can delete a task tag, detaching it from any tasks', function () {
    $this->actingAsSuperAdmin();
    $tag = TaskTag::factory()->create();
    $task = Task::factory()->create(['status_id' => TaskStatus::factory(), 'priority_id' => TaskPriority::factory()]);
    $task->tags()->attach($tag);

    $this->deleteJson("/api/task-tags/{$tag->id}")->assertNoContent();

    $this->assertDatabaseMissing('task_tags', ['id' => $tag->id]);
    $this->assertDatabaseMissing('task_tag_task', ['task_tag_id' => $tag->id]);
});
