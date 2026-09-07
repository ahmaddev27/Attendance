<?php

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

test('leave types index requires authentication', function () {
    $this->getJson('/api/leave-types')->assertUnauthorized();
});

test('an admin can list leave types', function () {
    $this->actingAsSuperAdmin();
    LeaveType::factory()->count(3)->create();

    $response = $this->getJson('/api/leave-types');

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('an admin can create a leave type', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/leave-types', [
        'name' => 'الإجازة السنوية',
        'code' => 'annual-test',
        'is_paid' => true,
        'is_balance_based' => true,
        'default_annual_entitlement' => 21,
        'min_notice_days' => 7,
        'color' => '#2678C4',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'annual-test')
        ->assertJsonPath('data.default_annual_entitlement', 21);

    $this->assertDatabaseHas('leave_types', ['code' => 'annual-test']);
});

test('creating a leave type validates required fields', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/leave-types', []);

    $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'code']);
});

test('leave type code must be unique', function () {
    $this->actingAsSuperAdmin();
    LeaveType::factory()->create(['code' => 'annual']);

    $response = $this->postJson('/api/leave-types', [
        'name' => 'Another Annual',
        'code' => 'annual',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

test('an admin can view a single leave type', function () {
    $this->actingAsSuperAdmin();
    $leaveType = LeaveType::factory()->create();

    $response = $this->getJson("/api/leave-types/{$leaveType->id}");

    $response->assertOk()->assertJsonPath('data.id', $leaveType->id);
});

test('an admin can update a leave type', function () {
    $this->actingAsSuperAdmin();
    $leaveType = LeaveType::factory()->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/leave-types/{$leaveType->id}", ['name' => 'New Name']);

    $response->assertOk()->assertJsonPath('data.name', 'New Name');
    $this->assertDatabaseHas('leave_types', ['id' => $leaveType->id, 'name' => 'New Name']);
});

test('an admin can delete an unused leave type', function () {
    $this->actingAsSuperAdmin();
    $leaveType = LeaveType::factory()->create();

    $response = $this->deleteJson("/api/leave-types/{$leaveType->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('leave_types', ['id' => $leaveType->id]);
});

test('deleting a leave type with existing leave requests is prevented', function () {
    $this->actingAsSuperAdmin();
    $leaveType = LeaveType::factory()->create();
    LeaveRequest::factory()->create(['leave_type_id' => $leaveType->id]);

    $response = $this->deleteJson("/api/leave-types/{$leaveType->id}");

    $response->assertUnprocessable();
    $this->assertDatabaseHas('leave_types', ['id' => $leaveType->id, 'deleted_at' => null]);
});

test('deleting a leave type with existing balances is prevented', function () {
    $this->actingAsSuperAdmin();
    $leaveType = LeaveType::factory()->create();
    LeaveBalance::factory()->create(['leave_type_id' => $leaveType->id]);

    $response = $this->deleteJson("/api/leave-types/{$leaveType->id}");

    $response->assertUnprocessable();
    $this->assertDatabaseHas('leave_types', ['id' => $leaveType->id, 'deleted_at' => null]);
});
