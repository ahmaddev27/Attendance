<?php

use App\Models\Department;
use App\Models\Position;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

beforeEach(function () {
    $this->actingAsSuperAdmin();
    $this->department = Department::factory()->create();
});

test('lists positions', function () {
    Position::factory()->count(3)->create(['department_id' => $this->department->id]);

    $response = $this->getJson('/api/org/positions');

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('creates a position', function () {
    $response = $this->postJson('/api/org/positions', [
        'department_id' => $this->department->id,
        'title' => 'Software Engineer',
        'code' => 'SWE',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Software Engineer')
        ->assertJsonPath('data.department_id', $this->department->id);

    $this->assertDatabaseHas('positions', ['title' => 'Software Engineer', 'department_id' => $this->department->id]);
});

test('creates a position without a department', function () {
    $response = $this->postJson('/api/org/positions', [
        'title' => 'Freelance Consultant',
    ]);

    $response->assertCreated()->assertJsonPath('data.department_id', null);
});

test('shows a position', function () {
    $position = Position::factory()->create(['department_id' => $this->department->id]);

    $response = $this->getJson("/api/org/positions/{$position->id}");

    $response->assertOk()->assertJsonPath('data.id', $position->id);
});

test('updates a position', function () {
    $position = Position::factory()->create(['department_id' => $this->department->id, 'title' => 'Old Title']);

    $response = $this->patchJson("/api/org/positions/{$position->id}", ['title' => 'New Title']);

    $response->assertOk()->assertJsonPath('data.title', 'New Title');
    $this->assertDatabaseHas('positions', ['id' => $position->id, 'title' => 'New Title']);
});

test('deletes a position', function () {
    $position = Position::factory()->create(['department_id' => $this->department->id]);

    $response = $this->deleteJson("/api/org/positions/{$position->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('positions', ['id' => $position->id]);
});
