<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

test('tasks report pdf downloads a real PDF with the expected filename', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();
    Task::factory()->create(['assigned_to' => $employee->id]);
    $year = now('Asia/Gaza')->year;

    $response = $this->get("/api/admin/employees/{$employee->id}/tasks-report/pdf?year={$year}");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Content-Disposition'))
        ->toMatch("/filename=\"?employee-{$employee->employee_number}-tasks-{$year}\.pdf\"?/");
});

test('tasks report pdf is forbidden without view-reports', function () {
    Sanctum::actingAs(User::factory()->create());
    $employee = Employee::factory()->create();

    $this->get("/api/admin/employees/{$employee->id}/tasks-report/pdf")->assertForbidden();
});
