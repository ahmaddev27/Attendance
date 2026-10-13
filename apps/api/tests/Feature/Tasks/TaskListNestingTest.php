<?php

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

function nestingTask(array $attrs = []): Task
{
    return Task::factory()->create($attrs + [
        'status_id' => TaskStatus::factory(),
        'priority_id' => TaskPriority::factory(),
    ]);
}

test('tree mode lists only top-level tasks with subtasks nested', function () {
    $this->actingAsSuperAdmin();
    $parent = nestingTask();
    $child = nestingTask(['parent_task_id' => $parent->id]);

    $res = $this->getJson('/api/tasks?tree=1')->assertOk();

    $res->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $parent->id)
        ->assertJsonPath('data.0.subtasks_count', 1)
        ->assertJsonCount(1, 'data.0.subtasks')
        ->assertJsonPath('data.0.subtasks.0.id', $child->id)
        ->assertJsonPath('data.0.subtasks.0.parent_task_id', $parent->id);
});

test('flat mode stays the default and does not nest', function () {
    $this->actingAsSuperAdmin();
    $parent = nestingTask();
    nestingTask(['parent_task_id' => $parent->id]);

    $this->getJson('/api/tasks')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.subtasks');
});

test('a matching subtask surfaces its parent in tree mode', function () {
    $this->actingAsSuperAdmin();
    $alice = Employee::factory()->create();
    $parent = nestingTask();
    $other = nestingTask();
    nestingTask(['parent_task_id' => $parent->id, 'assigned_to' => $alice->id]);

    $this->getJson("/api/tasks?tree=1&assigned_to={$alice->id}")->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $parent->id);

    expect($other->id)->not->toBe($parent->id);
});
