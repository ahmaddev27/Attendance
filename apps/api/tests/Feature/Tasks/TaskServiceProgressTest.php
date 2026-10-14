<?php

use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Modules\Tasks\Services\TaskService;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

function progressFixture(): array
{
    $open = TaskStatus::factory()->create(['sort_order' => 1, 'is_done_state' => false, 'is_cancelled_state' => false]);
    $done = TaskStatus::factory()->create(['sort_order' => 2, 'is_done_state' => true, 'is_cancelled_state' => false]);
    $priority = TaskPriority::factory()->create();
    $parent = Task::factory()->create(['status_id' => $open->id, 'priority_id' => $priority->id, 'progress_percent' => 0]);

    return [$open, $done, $priority, $parent];
}

test('creating a subtask under a parent bumps the parent progress', function () {
    $actor = $this->actingAsSuperAdmin();
    [$open, $done, $priority, $parent] = progressFixture();
    Task::factory()->create(['status_id' => $done->id, 'priority_id' => $priority->id, 'parent_task_id' => $parent->id]);

    app(TaskService::class)->create([
        'title' => 'second',
        'priority_id' => $priority->id,
        'status_id' => $open->id,
        'parent_task_id' => $parent->id,
    ], $actor);

    expect($parent->fresh()->progress_percent)->toBe(50);
});

test('completing a subtask bumps the parent progress', function () {
    $actor = $this->actingAsSuperAdmin();
    [$open, $done, $priority, $parent] = progressFixture();
    $child = Task::factory()->create(['status_id' => $open->id, 'priority_id' => $priority->id, 'parent_task_id' => $parent->id]);

    app(TaskService::class)->complete($child, $actor);

    expect($parent->fresh()->progress_percent)->toBe(100);
});

test('updating a subtask status recomputes the parent', function () {
    $actor = $this->actingAsSuperAdmin();
    [$open, $done, $priority, $parent] = progressFixture();
    $child = Task::factory()->create(['status_id' => $open->id, 'priority_id' => $priority->id, 'parent_task_id' => $parent->id]);
    Task::factory()->create(['status_id' => $open->id, 'priority_id' => $priority->id, 'parent_task_id' => $parent->id]);

    app(TaskService::class)->update($child, ['status_id' => $done->id], $actor);

    expect($parent->fresh()->progress_percent)->toBe(50);
});

test('deleting a subtask recomputes the parent progress', function () {
    $actor = $this->actingAsSuperAdmin();
    [$open, $done, $priority, $parent] = progressFixture();
    Task::factory()->create(['status_id' => $done->id, 'priority_id' => $priority->id, 'parent_task_id' => $parent->id]);
    $pending = Task::factory()->create(['status_id' => $open->id, 'priority_id' => $priority->id, 'parent_task_id' => $parent->id]);
    app(TaskService::class)->update($pending, ['status_id' => $open->id], $actor);
    $parent->update(['progress_percent' => 50]);

    app(TaskService::class)->delete($pending, $actor);

    expect($parent->fresh()->progress_percent)->toBe(100);
});

test('an explicit progress_percent on a leaf is ignored (auto-only, owner rule 2026-10-14)', function () {
    // Previously the service respected a manual value from the payload.
    // The owner removed the slider from the admin form and asked for the
    // percentage to be fully auto-computed. TaskService::update now
    // unsets progress_percent before persisting, so a stale FE client
    // cannot override the calculator's output.
    $actor = $this->actingAsSuperAdmin();
    [$open, , , $parent] = progressFixture();

    app(TaskService::class)->update($parent, ['progress_percent' => 40, 'status_id' => $open->id], $actor);

    // Leaf task in an "open" status with no subtasks → calculator keeps
    // the stored value (which started at 0 in the fixture) rather than
    // honouring the 40 the caller tried to force in.
    expect($parent->fresh()->progress_percent)->toBe(0);
});
