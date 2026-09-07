<?php

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\RequestType;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Shared\Enums\ApproverType;
use App\Shared\Enums\RequestStatus;
use Tests\Feature\Concerns\ActsAsEmployeeUser;

uses(ActsAsEmployeeUser::class);

/**
 * A 2-step workflow ("approver1 then approver2", both specific_employee
 * steps) with one pending request already sitting on step 1.
 *
 * @return array{request: RequestModel, step1: WorkflowStep, step2: WorkflowStep}
 */
function makeTwoStepPendingRequest(Employee $submitter, Employee $approver1, Employee $approver2, bool $canReject = true, bool $canReturn = false, bool $canForward = false): array
{
    $workflow = Workflow::factory()->create();

    $step1 = WorkflowStep::factory()->for($workflow)->order(1)
        ->approverType(ApproverType::SpecificEmployee, (string) $approver1->id)
        ->create(['can_reject' => $canReject, 'can_return' => $canReturn, 'can_forward' => $canForward]);

    $step2 = WorkflowStep::factory()->for($workflow)->order(2)
        ->approverType(ApproverType::SpecificEmployee, (string) $approver2->id)
        ->create();

    $requestType = RequestType::factory()->for($workflow)->create();

    $request = RequestModel::factory()->create([
        'employee_id' => $submitter->id,
        'request_type_id' => $requestType->id,
        'status' => RequestStatus::Pending,
        'current_step_id' => $step1->id,
    ]);

    return ['request' => $request, 'step1' => $step1, 'step2' => $step2];
}

test('a request moves through a 2-step workflow to completion', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    ['request' => $request, 'step2' => $step2] = makeTwoStepPendingRequest($submitter, $approver1, $approver2);

    $this->actingAsEmployeeUser($approver1);
    $this->postJson("/api/requests/{$request->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.current_step_id', $step2->id);

    $this->actingAsEmployeeUser($approver2);
    $this->postJson("/api/requests/{$request->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.current_step_id', null);

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Approved)
        ->and($request->completed_at)->not->toBeNull();
});

test('approving as someone who is not the current step\'s approver returns 403', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    $stranger = Employee::factory()->create();
    ['request' => $request] = makeTwoStepPendingRequest($submitter, $approver1, $approver2);

    $this->actingAsEmployeeUser($stranger);

    $this->postJson("/api/requests/{$request->id}/approve")->assertForbidden();
});

test('rejecting at step 1 marks the request rejected and blocks any further action', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    ['request' => $request] = makeTwoStepPendingRequest($submitter, $approver1, $approver2);

    $this->actingAsEmployeeUser($approver1);
    $this->postJson("/api/requests/{$request->id}/reject", ['comment' => 'Not justified.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->postJson("/api/requests/{$request->id}/approve")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('rejecting without a comment is rejected', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    ['request' => $request] = makeTwoStepPendingRequest($submitter, $approver1, $approver2);

    $this->actingAsEmployeeUser($approver1);

    $this->postJson("/api/requests/{$request->id}/reject", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['comment']);
});

test('returning a request sends it back to the employee and clears the current step', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    ['request' => $request] = makeTwoStepPendingRequest($submitter, $approver1, $approver2, canReturn: true);

    $this->actingAsEmployeeUser($approver1);

    $this->postJson("/api/requests/{$request->id}/return", ['comment' => 'Please add more detail.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'returned')
        ->assertJsonPath('data.current_step_id', null);
});

test('a step that disallows return rejects the return action', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    ['request' => $request] = makeTwoStepPendingRequest($submitter, $approver1, $approver2, canReturn: false);

    $this->actingAsEmployeeUser($approver1);

    $this->postJson("/api/requests/{$request->id}/return", ['comment' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['action']);
});

test('a step that disallows rejection rejects the reject action', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    ['request' => $request] = makeTwoStepPendingRequest($submitter, $approver1, $approver2, canReject: false);

    $this->actingAsEmployeeUser($approver1);

    $this->postJson("/api/requests/{$request->id}/reject", ['comment' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['action']);
});

test('forwarding routes the decision to a different approver without advancing the step', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    $delegate = Employee::factory()->create();
    ['request' => $request, 'step1' => $step1] = makeTwoStepPendingRequest($submitter, $approver1, $approver2, canForward: true);

    $this->actingAsEmployeeUser($approver1);
    $this->postJson("/api/requests/{$request->id}/forward", [
        'forwarded_to_id' => $delegate->id,
        'comment' => 'Please handle this while I am out.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.current_step_id', $step1->id);

    // The delegate was never the step's configured approver, but the
    // forward makes them an authorized alternate for this same step.
    $this->actingAsEmployeeUser($delegate);
    $this->postJson("/api/requests/{$request->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.current_step_id', fn ($value) => $value !== $step1->id);
});

test('forwarding when the step disallows it is rejected', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    $delegate = Employee::factory()->create();
    ['request' => $request] = makeTwoStepPendingRequest($submitter, $approver1, $approver2, canForward: false);

    $this->actingAsEmployeeUser($approver1);

    $this->postJson("/api/requests/{$request->id}/forward", [
        'forwarded_to_id' => $delegate->id,
        'comment' => 'x',
    ])->assertUnprocessable()->assertJsonValidationErrors(['action']);
});

test('a non-pending request cannot be approved even by a legitimately configured approver', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $workflow = Workflow::factory()->create();
    $requestType = RequestType::factory()->for($workflow)->create();

    $request = RequestModel::factory()->create([
        'employee_id' => $submitter->id,
        'request_type_id' => $requestType->id,
        'status' => RequestStatus::Approved,
        'current_step_id' => null,
    ]);

    $this->actingAsEmployeeUser($approver1);

    $this->postJson("/api/requests/{$request->id}/approve")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('the approver\'s inbox lists only requests currently pending on their step', function () {
    $submitter = Employee::factory()->create();
    $approver1 = Employee::factory()->create();
    $approver2 = Employee::factory()->create();
    makeTwoStepPendingRequest($submitter, $approver1, $approver2);

    $this->actingAsEmployeeUser($approver1);
    $this->getJson('/api/approvals/inbox')->assertOk()->assertJsonCount(1, 'data');

    $this->actingAsEmployeeUser($approver2);
    $this->getJson('/api/approvals/inbox')->assertOk()->assertJsonCount(0, 'data');
});
