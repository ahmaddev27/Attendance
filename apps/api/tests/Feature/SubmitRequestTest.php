<?php

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\RequestType;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Shared\Enums\ApproverType;
use Tests\Feature\Concerns\ActsAsEmployeeUser;

uses(ActsAsEmployeeUser::class);

/**
 * @param  array<int, array<string, mixed>>  $schema
 */
function makeComplaintRequestType(?array $schema = null, bool $withStep = true): RequestType
{
    $workflow = Workflow::factory()->create();

    if ($withStep) {
        WorkflowStep::factory()->for($workflow)->approverType(ApproverType::DepartmentManager)->create();
    }

    return RequestType::factory()->for($workflow)->withSchema($schema ?? [
        ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true],
        ['key' => 'details', 'label' => 'Details', 'type' => 'textarea', 'required' => true],
    ])->create();
}

test('an employee can submit a valid request and it becomes pending on the workflow\'s first step', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $requestType = makeComplaintRequestType();

    $response = $this->postJson('/api/me/requests', [
        'request_type_id' => $requestType->id,
        'form_data' => ['subject' => 'Noisy office', 'details' => 'It is too loud to focus.'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.request_number', 'REQ-0001');

    $this->assertDatabaseHas('requests', ['employee_id' => $employee->id, 'status' => 'pending']);
});

test('submitting against an inactive request type is rejected', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $requestType = makeComplaintRequestType();
    $requestType->update(['is_active' => false]);

    $response = $this->postJson('/api/me/requests', [
        'request_type_id' => $requestType->id,
        'form_data' => ['subject' => 'x', 'details' => 'y'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['request_type_id']);
});

test('submitting with missing required form fields is rejected', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $requestType = makeComplaintRequestType();

    $response = $this->postJson('/api/me/requests', [
        'request_type_id' => $requestType->id,
        'form_data' => ['subject' => 'Missing details'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['form_data.details']);
});

test('submitting with a form field of the wrong type is rejected', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $requestType = makeComplaintRequestType([
        ['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true],
    ]);

    $response = $this->postJson('/api/me/requests', [
        'request_type_id' => $requestType->id,
        'form_data' => ['amount' => 'not-a-number'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['form_data.amount']);
});

test('a request type whose workflow has no steps auto-approves on submission', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $requestType = makeComplaintRequestType(withStep: false);

    $response = $this->postJson('/api/me/requests', [
        'request_type_id' => $requestType->id,
        'form_data' => ['subject' => 'x', 'details' => 'y'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.current_step_id', null);

    $created = RequestModel::query()->latest('id')->first();
    expect($created->completed_at)->not->toBeNull();
});

test('request numbers are generated sequentially', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);
    $requestType = makeComplaintRequestType();

    $first = $this->postJson('/api/me/requests', [
        'request_type_id' => $requestType->id,
        'form_data' => ['subject' => 'first', 'details' => 'first'],
    ]);
    $second = $this->postJson('/api/me/requests', [
        'request_type_id' => $requestType->id,
        'form_data' => ['subject' => 'second', 'details' => 'second'],
    ]);

    $first->assertJsonPath('data.request_number', 'REQ-0001');
    $second->assertJsonPath('data.request_number', 'REQ-0002');
});

test('an employee can list and view only their own requests', function () {
    $employee = Employee::factory()->create();
    $other = Employee::factory()->create();
    $requestType = makeComplaintRequestType();

    $mine = RequestModel::factory()->create(['employee_id' => $employee->id, 'request_type_id' => $requestType->id]);
    RequestModel::factory()->create(['employee_id' => $other->id, 'request_type_id' => $requestType->id]);

    $this->actingAsEmployeeUser($employee);

    $this->getJson('/api/me/requests')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/me/requests/{$mine->id}")->assertOk();
});

test('an employee cannot view another employee\'s request', function () {
    $employee = Employee::factory()->create();
    $other = Employee::factory()->create();
    $requestType = makeComplaintRequestType();
    $theirs = RequestModel::factory()->create(['employee_id' => $other->id, 'request_type_id' => $requestType->id]);

    $this->actingAsEmployeeUser($employee);

    $this->getJson("/api/me/requests/{$theirs->id}")->assertForbidden();
});

test('an employee can cancel their own pending request', function () {
    $employee = Employee::factory()->create();
    $requestType = makeComplaintRequestType();
    $request = RequestModel::factory()->create(['employee_id' => $employee->id, 'request_type_id' => $requestType->id, 'status' => 'pending']);

    $this->actingAsEmployeeUser($employee);

    $response = $this->postJson("/api/me/requests/{$request->id}/cancel");

    $response->assertOk()->assertJsonPath('data.status', 'cancelled');
});

test('an employee cannot cancel another employee\'s request', function () {
    $employee = Employee::factory()->create();
    $other = Employee::factory()->create();
    $requestType = makeComplaintRequestType();
    $theirs = RequestModel::factory()->create(['employee_id' => $other->id, 'request_type_id' => $requestType->id, 'status' => 'pending']);

    $this->actingAsEmployeeUser($employee);

    $this->postJson("/api/me/requests/{$theirs->id}/cancel")->assertForbidden();
});

test('an already approved request cannot be cancelled', function () {
    $employee = Employee::factory()->create();
    $requestType = makeComplaintRequestType();
    $request = RequestModel::factory()->create(['employee_id' => $employee->id, 'request_type_id' => $requestType->id, 'status' => 'approved']);

    $this->actingAsEmployeeUser($employee);

    $this->postJson("/api/me/requests/{$request->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});
