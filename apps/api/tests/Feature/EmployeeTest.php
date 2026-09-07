<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use App\Shared\Enums\EmployeeStatus;
use Illuminate\Database\QueryException;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

beforeEach(function () {
    $this->actingAsSuperAdmin();
});

test('lists employees paginated', function () {
    Employee::factory()->count(30)->create();

    $response = $this->getJson('/api/employees');

    $response->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 30);
});

test('respects a custom per_page parameter', function () {
    Employee::factory()->count(15)->create();

    $response = $this->getJson('/api/employees?per_page=5');

    $response->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.per_page', 5);
});

test('filters employees by department', function () {
    $department = Department::factory()->create();
    Employee::factory()->count(2)->create(['department_id' => $department->id]);
    Employee::factory()->count(3)->create();

    $response = $this->getJson("/api/employees?department_id={$department->id}");

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('filters employees by team', function () {
    $department = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $department->id]);
    Employee::factory()->count(2)->create(['department_id' => $department->id, 'team_id' => $team->id]);
    Employee::factory()->count(2)->create();

    $response = $this->getJson("/api/employees?team_id={$team->id}");

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('filters employees by status', function () {
    Employee::factory()->count(2)->create(['status' => EmployeeStatus::Active]);
    Employee::factory()->count(3)->terminated()->create();

    $response = $this->getJson('/api/employees?status=terminated');

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('searches employees by first name', function () {
    Employee::factory()->create(['first_name' => 'Mohannad', 'last_name' => 'Zayed']);
    Employee::factory()->create(['first_name' => 'Farah', 'last_name' => 'Qasem']);

    $response = $this->getJson('/api/employees?search=Mohannad');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.first_name', 'Mohannad');
});

test('searches employees by full name', function () {
    Employee::factory()->create(['first_name' => 'Mohannad', 'last_name' => 'Zayed']);
    Employee::factory()->create(['first_name' => 'Farah', 'last_name' => 'Qasem']);

    $response = $this->getJson('/api/employees?search='.urlencode('Mohannad Zayed'));

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('searches employees by employee number', function () {
    $employee = Employee::factory()->create(['employee_number' => 4242]);
    Employee::factory()->create(['employee_number' => 9999]);

    $response = $this->getJson('/api/employees?search=4242');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $employee->id);
});

test('searches employees by phone', function () {
    $employee = Employee::factory()->create(['phone' => '0791234567']);
    Employee::factory()->create(['phone' => '0789999999']);

    $response = $this->getJson('/api/employees?search=1234567');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $employee->id);
});

test('creates an employee and auto-assigns the next employee number', function () {
    $payload = [
        'first_name' => 'Layla',
        'last_name' => 'Nassar',
        'email' => 'layla.nassar@taqat.local',
        'employment_type' => 'full_time',
        'joining_date' => '2026-01-15',
    ];

    $response = $this->postJson('/api/employees', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.first_name', 'Layla')
        ->assertJsonPath('data.employee_number', 1);

    $this->assertDatabaseHas('employees', ['email' => 'layla.nassar@taqat.local', 'employee_number' => 1]);
});

test('an attempt to submit a client-supplied employee_number is ignored', function () {
    $response = $this->postJson('/api/employees', [
        'employee_number' => 999999,
        'first_name' => 'Sami',
        'last_name' => 'Kanaan',
        'employment_type' => 'full_time',
        'joining_date' => '2026-01-15',
    ]);

    $response->assertCreated();
    expect($response->json('data.employee_number'))->not->toBe(999999);
});

test('sequential creates each get the next employee number with no gaps or duplicates', function () {
    $numbers = [];

    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/api/employees', [
            'first_name' => "Employee{$i}",
            'last_name' => 'Test',
            'employment_type' => 'full_time',
            'joining_date' => '2026-01-15',
        ]);

        $response->assertCreated();
        $numbers[] = $response->json('data.employee_number');
    }

    expect($numbers)->toBe([1, 2, 3, 4, 5]);
    expect(array_unique($numbers))->toHaveCount(5);
});

test('updates an employee', function () {
    $employee = Employee::factory()->create(['first_name' => 'Old']);

    $response = $this->patchJson("/api/employees/{$employee->id}", ['first_name' => 'New']);

    $response->assertOk()->assertJsonPath('data.first_name', 'New');
    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'first_name' => 'New']);
});

test('soft deletes and restores an employee', function () {
    $employee = Employee::factory()->create();

    $this->deleteJson("/api/employees/{$employee->id}")->assertOk();
    $this->assertSoftDeleted('employees', ['id' => $employee->id]);

    $this->getJson("/api/employees/{$employee->id}")->assertNotFound();

    $restoreResponse = $this->postJson("/api/employees/{$employee->id}/restore");

    $restoreResponse->assertOk()->assertJsonPath('data.id', $employee->id);
    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'deleted_at' => null]);
});

test('validates required fields when creating an employee', function () {
    $response = $this->postJson('/api/employees', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'employment_type', 'joining_date']);
});

test('prevents creating an employee with a duplicate email', function () {
    Employee::factory()->create(['email' => 'taken@taqat.local']);

    $response = $this->postJson('/api/employees', [
        'first_name' => 'Duplicate',
        'last_name' => 'Email',
        'email' => 'taken@taqat.local',
        'employment_type' => 'full_time',
        'joining_date' => '2026-01-15',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('the database rejects a duplicate employee_number', function () {
    Employee::factory()->create(['employee_number' => 7777]);

    expect(fn () => Employee::factory()->create(['employee_number' => 7777]))
        ->toThrow(QueryException::class);
});
