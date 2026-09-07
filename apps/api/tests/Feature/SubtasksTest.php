<?php

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Tests\Feature\Concerns\ActsAsEmployeeUser;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(ActsAsEmployeeUser::class, CreatesSuperAdmin::class);

test('a task can be created as a subtask of another via parent_task_id', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $priority = TaskPriority::factory()->create();
    $parent = Task::factory()->create(['created_by' => $employee->id, 'priority_id' => $priority->id, 'status_id' => TaskStatus::factory()]);

    $response = $this->postJson('/api/tasks', [
        'title' => 'Subtask of parent',
        'priority_id' => $priority->id,
        'parent_task_id' => $parent->id,
    ]);

    $response->assertCreated()->assertJsonPath('data.parent_task_id', $parent->id);
});

test('a parent task exposes its subtasks relationship', function () {
    $status = TaskStatus::factory()->create();
    $priority = TaskPriority::factory()->create();
    $parent = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id]);
    $child = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id, 'parent_task_id' => $parent->id]);

    expect($parent->subtasks()->pluck('id')->all())->toBe([$child->id]);
    expect($child->parent->id)->toBe($parent->id);
});

test('filtering tasks by parent_task_id=null returns only top-level tasks', function () {
    $this->actingAsSuperAdmin();
    $status = TaskStatus::factory()->create();
    $priority = TaskPriority::factory()->create();

    $topLevel = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id]);
    Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id, 'parent_task_id' => $topLevel->id]);

    $response = $this->getJson('/api/tasks?parent_task_id=null');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $topLevel->id);
});

test('filtering tasks by a specific parent_task_id returns only that parent\'s subtasks', function () {
    $this->actingAsSuperAdmin();
    $status = TaskStatus::factory()->create();
    $priority = TaskPriority::factory()->create();

    $parentA = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id]);
    $parentB = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id]);
    $childOfA = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id, 'parent_task_id' => $parentA->id]);
    Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id, 'parent_task_id' => $parentB->id]);

    $response = $this->getJson("/api/tasks?parent_task_id={$parentA->id}");

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $childOfA->id);
});

test('deleting a parent task cascades to its subtasks at the database level', function () {
    $status = TaskStatus::factory()->create();
    $priority = TaskPriority::factory()->create();
    $parent = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id]);
    $child = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id, 'parent_task_id' => $parent->id]);

    $parent->forceDelete();

    $this->assertDatabaseMissing('tasks', ['id' => $child->id]);
});
