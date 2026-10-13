<?php

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

function filterTask(array $attrs): Task
{
    return Task::factory()->create($attrs + [
        'status_id' => TaskStatus::factory(),
        'priority_id' => TaskPriority::factory(),
    ]);
}

test('assigned_to returns only matching rows', function () {
    $this->actingAsSuperAdmin();
    [$a, $b] = Employee::factory()->count(2)->create();
    $mine = filterTask(['assigned_to' => $a->id]);
    filterTask(['assigned_to' => $b->id]);

    $this->getJson("/api/tasks?assigned_to={$a->id}")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
});

test('created_by returns only matching rows', function () {
    $this->actingAsSuperAdmin();
    [$a, $b] = Employee::factory()->count(2)->create();
    $mine = filterTask(['created_by' => $a->id]);
    filterTask(['created_by' => $b->id]);

    $this->getJson("/api/tasks?created_by={$a->id}")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
});

test('combined filters AND together', function () {
    $this->actingAsSuperAdmin();
    [$a, $b] = Employee::factory()->count(2)->create();
    $both = filterTask(['created_by' => $a->id, 'assigned_to' => $b->id]);
    filterTask(['created_by' => $a->id, 'assigned_to' => $a->id]);
    filterTask(['created_by' => $b->id, 'assigned_to' => $b->id]);

    $this->getJson("/api/tasks?created_by={$a->id}&assigned_to={$b->id}")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $both->id);
});

test('unknown employee ids are rejected', function () {
    $this->actingAsSuperAdmin();
    $this->getJson('/api/tasks?assigned_to=999999')->assertUnprocessable();
});
