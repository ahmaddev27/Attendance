<?php

use App\Models\Department;
use App\Models\Team;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

beforeEach(function () {
    $this->actingAsSuperAdmin();
    $this->department = Department::factory()->create();
});

test('lists teams', function () {
    Team::factory()->count(2)->create(['department_id' => $this->department->id]);

    $response = $this->getJson('/api/org/teams');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('creates a team belonging to a department', function () {
    $response = $this->postJson('/api/org/teams', [
        'department_id' => $this->department->id,
        'name' => 'Backend',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Backend')
        ->assertJsonPath('data.department_id', $this->department->id);

    $this->assertDatabaseHas('teams', [
        'name' => 'Backend',
        'department_id' => $this->department->id,
    ]);
});

test('shows a team with its department', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id]);

    $response = $this->getJson("/api/org/teams/{$team->id}");

    $response->assertOk()->assertJsonPath('data.department_id', $this->department->id);
});

test('updates a team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id, 'name' => 'Old Name']);

    $response = $this->patchJson("/api/org/teams/{$team->id}", ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
    $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'New Name']);
});

test('moving a team to another department updates its department_id', function () {
    $otherDepartment = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $this->department->id]);

    $response = $this->patchJson("/api/org/teams/{$team->id}", [
        'department_id' => $otherDepartment->id,
    ]);

    $response->assertOk()->assertJsonPath('data.department_id', $otherDepartment->id);
});

test('deletes a team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id]);

    $response = $this->deleteJson("/api/org/teams/{$team->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});
