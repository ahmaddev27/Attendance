<?php

use App\Models\RequestType;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Shared\Enums\ApproverType;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

test('an admin can create a workflow', function () {
    $this->actingAsSuperAdmin();

    $response = $this->postJson('/api/workflows', [
        'name' => 'Business Mission Approval',
        'description' => 'Employee -> Direct Manager -> Department Manager',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Business Mission Approval')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('workflows', ['name' => 'Business Mission Approval']);
});

test('an admin can list workflows with their steps', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();
    WorkflowStep::factory()->for($workflow)->order(1)->create();

    $response = $this->getJson('/api/workflows');

    $response->assertOk()->assertJsonPath('data.0.steps.0.step_order', 1);
});

test('an admin can view a single workflow', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->getJson("/api/workflows/{$workflow->id}");

    $response->assertOk()->assertJsonPath('data.id', $workflow->id);
});

test('an admin can update a workflow', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create(['is_active' => true]);

    $response = $this->putJson("/api/workflows/{$workflow->id}", ['is_active' => false]);

    $response->assertOk()->assertJsonPath('data.is_active', false);
});

test('an admin can delete a workflow that is not used by any request type', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->deleteJson("/api/workflows/{$workflow->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
});

test('deleting a workflow used by a request type is rejected', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();
    RequestType::factory()->for($workflow)->create();

    $response = $this->deleteJson("/api/workflows/{$workflow->id}");

    $response->assertUnprocessable()->assertJsonValidationErrors(['workflow']);
    $this->assertDatabaseHas('workflows', ['id' => $workflow->id]);
});

test('an admin can add a step to a workflow', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->postJson("/api/workflows/{$workflow->id}/steps", [
        'name' => 'موافقة المدير المباشر',
        'approver_type' => ApproverType::DirectManager->value,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.approver_type', 'direct_manager')
        ->assertJsonPath('data.step_order', 1);
});

test('a step appended without an explicit order gets the next sequential order', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();
    WorkflowStep::factory()->for($workflow)->order(1)->create();

    $response = $this->postJson("/api/workflows/{$workflow->id}/steps", [
        'name' => 'Second step',
        'approver_type' => ApproverType::DirectManager->value,
    ]);

    $response->assertCreated()->assertJsonPath('data.step_order', 2);
});

test('adding a specific_employee step without approver_ref is rejected', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->postJson("/api/workflows/{$workflow->id}/steps", [
        'name' => 'Specific approver',
        'approver_type' => ApproverType::SpecificEmployee->value,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['approver_ref']);
});

test('an admin can update a workflow step', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();
    $step = WorkflowStep::factory()->for($workflow)->create(['can_reject' => true]);

    $response = $this->putJson("/api/workflows/{$workflow->id}/steps/{$step->id}", [
        'can_reject' => false,
    ]);

    $response->assertOk()->assertJsonPath('data.can_reject', false);
});

test('a step belonging to a different workflow returns 404', function () {
    $this->actingAsSuperAdmin();
    $workflowA = Workflow::factory()->create();
    $workflowB = Workflow::factory()->create();
    $step = WorkflowStep::factory()->for($workflowB)->create();

    $response = $this->getJson("/api/workflows/{$workflowA->id}/steps/{$step->id}");

    $response->assertNotFound();
});

test('an admin can delete a workflow step', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();
    $step = WorkflowStep::factory()->for($workflow)->create();

    $response = $this->deleteJson("/api/workflows/{$workflow->id}/steps/{$step->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('workflow_steps', ['id' => $step->id]);
});

test('an admin can reorder a workflow\'s steps', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();
    $step1 = WorkflowStep::factory()->for($workflow)->order(1)->create();
    $step2 = WorkflowStep::factory()->for($workflow)->order(2)->create();

    $response = $this->postJson("/api/workflows/{$workflow->id}/steps/reorder", [
        'steps' => [
            ['id' => $step1->id, 'step_order' => 2],
            ['id' => $step2->id, 'step_order' => 1],
        ],
    ]);

    $response->assertOk();

    expect($step1->fresh()->step_order)->toBe(2)
        ->and($step2->fresh()->step_order)->toBe(1);
});

test('reordering with a step that does not belong to the workflow is rejected', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();
    $step1 = WorkflowStep::factory()->for($workflow)->order(1)->create();
    $foreignStep = WorkflowStep::factory()->create();

    $response = $this->postJson("/api/workflows/{$workflow->id}/steps/reorder", [
        'steps' => [
            ['id' => $step1->id, 'step_order' => 2],
            ['id' => $foreignStep->id, 'step_order' => 1],
        ],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['steps']);
});
