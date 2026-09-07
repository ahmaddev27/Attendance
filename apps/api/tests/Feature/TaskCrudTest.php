<?php

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskTag;
use Tests\Feature\Concerns\ActsAsEmployeeUser;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(ActsAsEmployeeUser::class, CreatesSuperAdmin::class);

function makeTaskStatus(array $overrides = []): TaskStatus
{
    return TaskStatus::factory()->create($overrides);
}

function makeTaskPriority(array $overrides = []): TaskPriority
{
    return TaskPriority::factory()->create($overrides);
}

test('tasks index requires authentication', function () {
    $this->getJson('/api/tasks')->assertUnauthorized();
});

test('an employee can create a task for themselves without passing created_by', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    makeTaskStatus();
    $priority = makeTaskPriority();

    $response = $this->postJson('/api/tasks', [
        'title' => 'Prepare sprint report',
        'priority_id' => $priority->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Prepare sprint report')
        ->assertJsonPath('data.creator.id', $employee->id);

    expect(TaskHistory::query()
        ->where('task_id', $response->json('data.id'))
        ->where('action', 'created')
        ->exists())->toBeTrue();
});

test('creating a task without an employee profile and without created_by fails validation', function () {
    $this->actingAsSuperAdmin();
    $priority = makeTaskPriority();

    $response = $this->postJson('/api/tasks', [
        'title' => 'Orphan task',
        'priority_id' => $priority->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['created_by']);
});

test('an admin can create a task on behalf of an employee via created_by', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();
    makeTaskStatus();
    $priority = makeTaskPriority();

    $response = $this->postJson('/api/tasks', [
        'title' => 'Admin-assigned task',
        'priority_id' => $priority->id,
        'created_by' => $employee->id,
    ]);

    $response->assertCreated()->assertJsonPath('data.creator.id', $employee->id);
});

test('creating a task with an assignee logs an assigned history entry', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $assignee = Employee::factory()->create();
    makeTaskStatus();
    $priority = makeTaskPriority();

    $response = $this->postJson('/api/tasks', [
        'title' => 'Review pull request',
        'priority_id' => $priority->id,
        'assigned_to' => $assignee->id,
    ]);

    $response->assertCreated()->assertJsonPath('data.assignee.id', $assignee->id);

    expect(TaskHistory::query()
        ->where('task_id', $response->json('data.id'))
        ->where('action', 'assigned')
        ->exists())->toBeTrue();
});

test('a new task defaults to the first status by sort order when none is given', function () {
    makeTaskStatus(['code' => 'backlog', 'sort_order' => 1]);
    makeTaskStatus(['code' => 'in_progress', 'sort_order' => 2]);
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $priority = makeTaskPriority();

    $response = $this->postJson('/api/tasks', [
        'title' => 'Uses default status',
        'priority_id' => $priority->id,
    ]);

    $response->assertCreated()->assertJsonPath('data.status.code', 'backlog');
});

test('updating a task status logs a status_changed history entry', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $status = makeTaskStatus();
    $newStatus = makeTaskStatus();
    $priority = makeTaskPriority();

    $task = Task::factory()->create([
        'created_by' => $employee->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
    ]);

    $response = $this->putJson("/api/tasks/{$task->id}", ['status_id' => $newStatus->id]);

    $response->assertOk()->assertJsonPath('data.status.id', $newStatus->id);

    expect(TaskHistory::query()
        ->where('task_id', $task->id)
        ->where('action', 'status_changed')
        ->exists())->toBeTrue();
});

test('assigning a task via update logs an assigned history entry, unassigning logs unassigned', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $assignee = Employee::factory()->create();
    $status = makeTaskStatus();
    $priority = makeTaskPriority();

    $task = Task::factory()->create([
        'created_by' => $employee->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'assigned_to' => null,
    ]);

    $this->putJson("/api/tasks/{$task->id}", ['assigned_to' => $assignee->id])->assertOk();

    expect(TaskHistory::query()->where('task_id', $task->id)->where('action', 'assigned')->exists())->toBeTrue();

    $this->putJson("/api/tasks/{$task->id}", ['assigned_to' => null])->assertOk();

    expect(TaskHistory::query()->where('task_id', $task->id)->where('action', 'unassigned')->exists())->toBeTrue();
});

test('completing a task via the complete endpoint moves it to the first done state and stamps completed_at', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $status = makeTaskStatus(['code' => 'todo', 'sort_order' => 1]);
    $doneStatus = makeTaskStatus(['code' => 'done', 'sort_order' => 5, 'is_done_state' => true]);
    $priority = makeTaskPriority();

    $task = Task::factory()->create([
        'created_by' => $employee->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'completed_at' => null,
    ]);

    $response = $this->postJson("/api/tasks/{$task->id}/complete");

    $response->assertOk()
        ->assertJsonPath('data.status.id', $doneStatus->id)
        ->assertJsonPath('data.progress_percent', 100);

    $task->refresh();
    expect($task->completed_at)->not->toBeNull();

    expect(TaskHistory::query()->where('task_id', $task->id)->where('action', 'completed')->exists())->toBeTrue();
});

test('the task index can filter by assignee, status, priority, tag, and search', function () {
    $this->actingAsSuperAdmin();

    $statusA = makeTaskStatus();
    $statusB = makeTaskStatus();
    $priorityA = makeTaskPriority();
    $priorityB = makeTaskPriority();
    $assignee = Employee::factory()->create();
    $tag = TaskTag::factory()->create();

    $target = Task::factory()->create([
        'title' => 'Findable unique task title',
        'status_id' => $statusA->id,
        'priority_id' => $priorityA->id,
        'assigned_to' => $assignee->id,
    ]);
    $target->tags()->attach($tag);

    Task::factory()->create(['status_id' => $statusB->id, 'priority_id' => $priorityB->id]);
    Task::factory()->create(['status_id' => $statusB->id, 'priority_id' => $priorityB->id]);

    $this->getJson("/api/tasks?assigned_to={$assignee->id}")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);

    $this->getJson("/api/tasks?status_id={$statusA->id}")
        ->assertOk()->assertJsonCount(1, 'data');

    $this->getJson("/api/tasks?priority_id={$priorityA->id}")
        ->assertOk()->assertJsonCount(1, 'data');

    $this->getJson("/api/tasks?tag_id={$tag->id}")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);

    $this->getJson('/api/tasks?search=Findable+unique')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

test('the kanban endpoint returns every status, tasks grouped under status.code', function () {
    $this->actingAsSuperAdmin();

    $todo = makeTaskStatus(['code' => 'todo', 'sort_order' => 1]);
    $inProgress = makeTaskStatus(['code' => 'in_progress', 'sort_order' => 2]);
    $priority = makeTaskPriority();

    $todoTask = Task::factory()->create(['status_id' => $todo->id, 'priority_id' => $priority->id]);
    Task::factory()->create(['status_id' => $inProgress->id, 'priority_id' => $priority->id]);

    $response = $this->getJson('/api/tasks/kanban');

    $response->assertOk();

    $data = $response->json('data');

    expect($data)->toHaveKeys(['todo', 'in_progress']);
    expect($data['todo']['tasks'])->toHaveCount(1);
    expect($data['todo']['tasks'][0]['id'])->toBe($todoTask->id);
    expect($data['in_progress']['tasks'])->toHaveCount(1);
});

test('a task can be soft deleted and then restored', function () {
    $this->actingAsSuperAdmin();
    $status = makeTaskStatus();
    $priority = makeTaskPriority();
    $task = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id]);

    $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    $this->getJson('/api/tasks')->assertJsonMissing(['id' => $task->id]);

    $response = $this->postJson("/api/tasks/{$task->id}/restore");

    $response->assertOk()->assertJsonPath('data.id', $task->id);
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);

    expect(TaskHistory::query()->where('task_id', $task->id)->where('action', 'deleted')->exists())->toBeTrue();
    expect(TaskHistory::query()->where('task_id', $task->id)->where('action', 'restored')->exists())->toBeTrue();
});

test('showing a task returns its full detail payload with subtasks, comments, and history', function () {
    $this->actingAsSuperAdmin();
    $status = makeTaskStatus();
    $priority = makeTaskPriority();
    $task = Task::factory()->create(['status_id' => $status->id, 'priority_id' => $priority->id]);
    Task::factory()->create(['parent_task_id' => $task->id, 'status_id' => $status->id, 'priority_id' => $priority->id]);

    $response = $this->getJson("/api/tasks/{$task->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonCount(1, 'data.subtasks')
        ->assertJsonStructure(['data' => ['history', 'comments', 'attachments']]);
});
