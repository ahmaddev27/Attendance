<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

function reportUrl(Employee $employee, string $query = ''): string
{
    return "/api/admin/employees/{$employee->id}/tasks-report{$query}";
}

function gazaYear(): int
{
    return CarbonImmutable::now('Asia/Gaza')->year;
}

function makeTask(array $attributes): Task
{
    $task = Task::factory()->create($attributes);

    return $task;
}

beforeEach(function (): void {
    $this->actingAsSuperAdmin();
    $this->employee = Employee::factory()->create();
    $this->pending = TaskStatus::factory()->create();
    $this->done = TaskStatus::factory()->doneState()->create();
    $this->cancelled = TaskStatus::factory()->cancelledState()->create();
    $this->midYear = CarbonImmutable::create(gazaYear(), 6, 15, 12, 0, 0, 'Asia/Gaza')->utc();
});

test('returns one row per task the employee is assigned or created', function () {
    makeTask(['assigned_to' => $this->employee->id, 'status_id' => $this->pending->id, 'created_at' => $this->midYear]);
    makeTask(['created_by' => $this->employee->id, 'status_id' => $this->done->id, 'created_at' => $this->midYear, 'completed_at' => $this->midYear]);
    makeTask(['status_id' => $this->pending->id, 'created_at' => $this->midYear]);

    $response = $this->getJson(reportUrl($this->employee))->assertOk();

    $response->assertJsonCount(2, 'tasks');
    expect($response->json('totals.total'))->toBe(2)
        ->and($response->json('totals.pending'))->toBe(1)
        ->and($response->json('totals.completed'))->toBe(1)
        ->and($response->json('by_month.5.assigned'))->toBe(2)
        ->and($response->json('by_month.5.completed'))->toBe(1);
});

test('splits assignee and creator roles and lists a self-assigned task once as assignee', function () {
    makeTask(['assigned_to' => $this->employee->id, 'created_at' => $this->midYear]);
    makeTask(['created_by' => $this->employee->id, 'created_at' => $this->midYear]);
    makeTask(['created_by' => $this->employee->id, 'assigned_to' => $this->employee->id, 'created_at' => $this->midYear]);

    $tasks = collect($this->getJson(reportUrl($this->employee))->assertOk()->json('tasks'));

    expect($tasks)->toHaveCount(3)
        ->and($tasks->where('assigned_role', 'assignee'))->toHaveCount(2)
        ->and($tasks->where('assigned_role', 'creator'))->toHaveCount(1);
});

test('flags overdue only for open tasks past their Gaza due date', function () {
    $past = CarbonImmutable::now('Asia/Gaza')->subDays(3)->toDateString();
    $future = CarbonImmutable::now('Asia/Gaza')->addDays(3)->toDateString();
    $base = ['assigned_to' => $this->employee->id, 'created_at' => CarbonImmutable::now()];

    makeTask($base + ['status_id' => $this->pending->id, 'due_date' => $past]);
    makeTask($base + ['status_id' => $this->pending->id, 'due_date' => $future]);
    makeTask($base + ['status_id' => $this->done->id, 'due_date' => $past, 'completed_at' => now()]);
    makeTask($base + ['status_id' => $this->cancelled->id, 'due_date' => $past]);

    $response = $this->getJson(reportUrl($this->employee))->assertOk();

    expect($response->json('totals.overdue'))->toBe(1)
        ->and($response->json('totals.cancelled'))->toBe(1)
        ->and(collect($response->json('tasks'))->where('is_overdue', true))->toHaveCount(1);
});

test('month filter restricts the window', function () {
    $june = $this->midYear;
    $march = CarbonImmutable::create(gazaYear(), 3, 10, 12, 0, 0, 'Asia/Gaza')->utc();
    makeTask(['assigned_to' => $this->employee->id, 'created_at' => $june]);
    makeTask(['assigned_to' => $this->employee->id, 'created_at' => $march]);

    $this->getJson(reportUrl($this->employee, '?month=6'))->assertOk()->assertJsonCount(1, 'tasks');
    $this->getJson(reportUrl($this->employee))->assertOk()->assertJsonCount(2, 'tasks');
    $this->getJson(reportUrl($this->employee, '?year='.(gazaYear() - 1)))->assertOk()->assertJsonCount(0, 'tasks');
});

test('is forbidden without the view-reports permission', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(reportUrl($this->employee))->assertForbidden();
});
