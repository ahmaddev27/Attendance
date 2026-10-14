<?php

use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

function shapeTask(array $attrs = []): Task
{
    return Task::factory()->create($attrs + [
        'status_id' => TaskStatus::factory(),
        'priority_id' => TaskPriority::factory(),
    ]);
}

test('list accepts the literal string "true" that axios sends for tree', function () {
    // Regression 2026-10-14: the admin page sends `tree: true`, which axios
    // serialises as `?tree=true`. Laravel's `boolean` rule rejects "true"
    // (only 1/0/"1"/"0"/true/false pass), so every list load was a 422.
    $this->actingAsSuperAdmin();
    $parent = shapeTask();
    shapeTask(['parent_task_id' => $parent->id]);

    $this->getJson('/api/tasks?tree=true&page=1&per_page=20')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $parent->id);

    $this->getJson('/api/tasks?tree=false')->assertOk()->assertJsonCount(2, 'data');
});

test('kanban ignores the company_id the web board always sends and shows tasks', function () {
    $this->actingAsSuperAdmin();
    $task = shapeTask();

    $res = $this->getJson('/api/tasks/kanban?company_id=')->assertOk();

    $ids = collect($res->json('data'))->flatMap(fn ($c) => collect($c['tasks'])->pluck('id'))->all();
    expect($ids)->toContain($task->id);
});
