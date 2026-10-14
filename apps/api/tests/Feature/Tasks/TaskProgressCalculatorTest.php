<?php

use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Modules\Tasks\Services\TaskProgressCalculator;

function calcStatus(bool $done = false, bool $cancelled = false): TaskStatus
{
    return TaskStatus::factory()->create(['is_done_state' => $done, 'is_cancelled_state' => $cancelled]);
}

function calcTask(TaskStatus $status, array $attrs = []): Task
{
    return Task::factory()->create($attrs + [
        'status_id' => $status->id,
        'priority_id' => TaskPriority::factory(),
    ]);
}

function calcFor(Task $task): int
{
    return app(TaskProgressCalculator::class)->compute($task->fresh());
}

test('leaf task in a done status is 100', function () {
    expect(calcFor(calcTask(calcStatus(done: true), ['progress_percent' => 20])))->toBe(100);
});

test('leaf task in an open status keeps its manual value', function () {
    expect(calcFor(calcTask(calcStatus(), ['progress_percent' => 35])))->toBe(35);
});

test('parent with 3 subtasks, 1 done and 1 in progress, is 33', function () {
    $parent = calcTask(calcStatus());
    $done = calcStatus(done: true);
    $open = calcStatus();
    calcTask($done, ['parent_task_id' => $parent->id]);
    calcTask($open, ['parent_task_id' => $parent->id]);
    calcTask($open, ['parent_task_id' => $parent->id]);

    expect(calcFor($parent))->toBe(33);
});

test('cancelled subtasks leave the denominator', function () {
    $parent = calcTask(calcStatus());
    calcTask(calcStatus(done: true), ['parent_task_id' => $parent->id]);
    calcTask(calcStatus(cancelled: true), ['parent_task_id' => $parent->id]);

    expect(calcFor($parent))->toBe(100);
});

test('a parent whose only subtask is cancelled is 100', function () {
    $parent = calcTask(calcStatus());
    calcTask(calcStatus(cancelled: true), ['parent_task_id' => $parent->id]);

    expect(calcFor($parent))->toBe(100);
});
