<?php

use App\Models\Company;
use App\Models\Department;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

beforeEach(function () {
    $this->actingAsSuperAdmin();
    $this->company = Company::factory()->create();
});

test('lists departments', function () {
    Department::factory()->count(3)->create(['company_id' => $this->company->id]);

    $response = $this->getJson('/api/org/departments');

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('creates a department', function () {
    $payload = [
        'company_id' => $this->company->id,
        'name' => 'التطوير',
        'code' => 'DEV',
        'description' => 'Software engineering department',
    ];

    $response = $this->postJson('/api/org/departments', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'التطوير')
        ->assertJsonPath('data.code', 'DEV')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('departments', [
        'name' => 'التطوير',
        'code' => 'DEV',
        'company_id' => $this->company->id,
    ]);
});

test('updates a department renaming it and changing its parent', function () {
    $parent = Department::factory()->create(['company_id' => $this->company->id, 'name' => 'Old Parent']);
    $newParent = Department::factory()->create(['company_id' => $this->company->id, 'name' => 'New Parent']);
    $department = Department::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Original Name',
        'parent_id' => $parent->id,
    ]);

    $response = $this->patchJson("/api/org/departments/{$department->id}", [
        'name' => 'Renamed Department',
        'parent_id' => $newParent->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Renamed Department')
        ->assertJsonPath('data.parent_id', $newParent->id);

    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'name' => 'Renamed Department',
        'parent_id' => $newParent->id,
    ]);
});

test('prevents a department becoming its own ancestor', function () {
    $grandparent = Department::factory()->create(['company_id' => $this->company->id]);
    $parent = Department::factory()->create(['company_id' => $this->company->id, 'parent_id' => $grandparent->id]);
    $child = Department::factory()->create(['company_id' => $this->company->id, 'parent_id' => $parent->id]);

    // Attempting to make the grandparent a child of its own grandchild
    // would create a cycle: grandparent -> parent -> child -> grandparent.
    $response = $this->patchJson("/api/org/departments/{$grandparent->id}", [
        'parent_id' => $child->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('parent_id');

    $this->assertDatabaseHas('departments', [
        'id' => $grandparent->id,
        'parent_id' => null,
    ]);
});

test('rejects a department being set as its own parent', function () {
    $department = Department::factory()->create(['company_id' => $this->company->id]);

    $response = $this->patchJson("/api/org/departments/{$department->id}", [
        'parent_id' => $department->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('soft deletes a department', function () {
    $department = Department::factory()->create(['company_id' => $this->company->id]);

    $response = $this->deleteJson("/api/org/departments/{$department->id}");

    $response->assertOk();

    $this->assertSoftDeleted('departments', ['id' => $department->id]);

    $this->getJson('/api/org/departments')->assertJsonMissing(['id' => $department->id]);
});

test('lists only active departments when filtered', function () {
    Department::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
    Department::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
    Department::factory()->create(['company_id' => $this->company->id, 'is_active' => false]);

    $response = $this->getJson('/api/org/departments?active=true');

    $response->assertOk()->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $department) {
        expect($department['is_active'])->toBeTrue();
    }
});
