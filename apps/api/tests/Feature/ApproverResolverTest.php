<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\RequestApproval;
use App\Models\RequestType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Modules\Requests\Services\ApproverResolver;
use App\Shared\Enums\ApprovalAction;
use App\Shared\Enums\ApproverType;
use App\Shared\Enums\RequestStatus;
use Spatie\Permission\Models\Role;

/**
 * Builds a bare Request (no workflow steps of its own) whose employee is
 * $submitter, purely as the "$request" argument ApproverResolver::resolve()
 * needs to look at $request->employee.
 */
function makeRequestFor(Employee $submitter): RequestModel
{
    $requestType = RequestType::factory()->create();

    return RequestModel::factory()->create([
        'employee_id' => $submitter->id,
        'request_type_id' => $requestType->id,
    ]);
}

test('direct_manager resolves to the submitting employee\'s direct manager', function () {
    $manager = Employee::factory()->create();
    $submitter = Employee::factory()->create(['direct_manager_id' => $manager->id]);
    $step = WorkflowStep::factory()->approverType(ApproverType::DirectManager)->create();
    $request = makeRequestFor($submitter);

    $resolved = (new ApproverResolver)->resolve($step, $request);

    expect($resolved->pluck('id')->all())->toBe([$manager->id]);
});

test('direct_manager resolves to no one when the employee has no manager', function () {
    $submitter = Employee::factory()->create(['direct_manager_id' => null]);
    $step = WorkflowStep::factory()->approverType(ApproverType::DirectManager)->create();
    $request = makeRequestFor($submitter);

    expect((new ApproverResolver)->resolve($step, $request))->toBeEmpty();
});

test('department_manager resolves to the submitting employee\'s department manager', function () {
    $deptManager = Employee::factory()->create();
    $department = Department::factory()->create(['manager_id' => $deptManager->id]);
    $submitter = Employee::factory()->create(['department_id' => $department->id]);
    $step = WorkflowStep::factory()->approverType(ApproverType::DepartmentManager)->create();
    $request = makeRequestFor($submitter);

    $resolved = (new ApproverResolver)->resolve($step, $request);

    expect($resolved->pluck('id')->all())->toBe([$deptManager->id]);
});

test('specific_employee resolves to the referenced employee', function () {
    $approver = Employee::factory()->create();
    $submitter = Employee::factory()->create();
    $step = WorkflowStep::factory()->approverType(ApproverType::SpecificEmployee, (string) $approver->id)->create();
    $request = makeRequestFor($submitter);

    $resolved = (new ApproverResolver)->resolve($step, $request);

    expect($resolved->pluck('id')->all())->toBe([$approver->id]);
});

test('specific_role resolves to every employee whose user holds that role', function () {
    Role::findOrCreate('finance');
    $financeEmployee = Employee::factory()->create();
    User::factory()->create(['employee_id' => $financeEmployee->id])->assignRole('finance');

    $submitter = Employee::factory()->create();
    $step = WorkflowStep::factory()->approverType(ApproverType::SpecificRole, 'finance')->create();
    $request = makeRequestFor($submitter);

    $resolved = (new ApproverResolver)->resolve($step, $request);

    expect($resolved->pluck('id')->all())->toBe([$financeEmployee->id]);
});

test('specific_role resolves to no one when the role has not been seeded yet', function () {
    $submitter = Employee::factory()->create();
    $step = WorkflowStep::factory()->approverType(ApproverType::SpecificRole, 'not-a-real-role')->create();
    $request = makeRequestFor($submitter);

    expect((new ApproverResolver)->resolve($step, $request))->toBeEmpty();
});

test('form_field resolves the employee referenced by the named form_data key', function () {
    $reviewer = Employee::factory()->create();
    $submitter = Employee::factory()->create();
    $step = WorkflowStep::factory()->approverType(ApproverType::FormField, 'reviewer_id')->create();
    $requestType = RequestType::factory()->create();

    $request = RequestModel::factory()->create([
        'employee_id' => $submitter->id,
        'request_type_id' => $requestType->id,
        'form_data' => ['reviewer_id' => $reviewer->id],
    ]);

    $resolved = (new ApproverResolver)->resolve($step, $request);

    expect($resolved->pluck('id')->all())->toBe([$reviewer->id]);
});

test('isAuthorized is true for the resolved approver and false for anyone else', function () {
    $approver = Employee::factory()->create();
    $stranger = Employee::factory()->create();
    $submitter = Employee::factory()->create();
    $workflow = Workflow::factory()->create();
    $step = WorkflowStep::factory()->for($workflow)->approverType(ApproverType::SpecificEmployee, (string) $approver->id)->create();
    $requestType = RequestType::factory()->for($workflow)->create();

    $request = RequestModel::factory()->create([
        'employee_id' => $submitter->id,
        'request_type_id' => $requestType->id,
        'status' => RequestStatus::Pending,
        'current_step_id' => $step->id,
    ]);

    $resolver = new ApproverResolver;

    expect($resolver->isAuthorized($request, $approver))->toBeTrue()
        ->and($resolver->isAuthorized($request, $stranger))->toBeFalse();
});

test('isAuthorized is true for an employee a decision was recently forwarded to', function () {
    $approver = Employee::factory()->create();
    $delegate = Employee::factory()->create();
    $submitter = Employee::factory()->create();
    $workflow = Workflow::factory()->create();
    $step = WorkflowStep::factory()->for($workflow)->approverType(ApproverType::SpecificEmployee, (string) $approver->id)->create();
    $requestType = RequestType::factory()->for($workflow)->create();

    $request = RequestModel::factory()->create([
        'employee_id' => $submitter->id,
        'request_type_id' => $requestType->id,
        'status' => RequestStatus::Pending,
        'current_step_id' => $step->id,
    ]);

    RequestApproval::factory()->create([
        'request_id' => $request->id,
        'workflow_step_id' => $step->id,
        'approver_id' => $approver->id,
        'action' => ApprovalAction::Forwarded,
        'forwarded_to_id' => $delegate->id,
        'decided_at' => now(),
    ]);

    expect((new ApproverResolver)->isAuthorized($request, $delegate))->toBeTrue();
});

test('isAuthorized is false once the forward window has expired', function () {
    $approver = Employee::factory()->create();
    $delegate = Employee::factory()->create();
    $submitter = Employee::factory()->create();
    $workflow = Workflow::factory()->create();
    $step = WorkflowStep::factory()->for($workflow)->approverType(ApproverType::SpecificEmployee, (string) $approver->id)->create();
    $requestType = RequestType::factory()->for($workflow)->create();

    $request = RequestModel::factory()->create([
        'employee_id' => $submitter->id,
        'request_type_id' => $requestType->id,
        'status' => RequestStatus::Pending,
        'current_step_id' => $step->id,
    ]);

    RequestApproval::factory()->create([
        'request_id' => $request->id,
        'workflow_step_id' => $step->id,
        'approver_id' => $approver->id,
        'action' => ApprovalAction::Forwarded,
        'forwarded_to_id' => $delegate->id,
        'decided_at' => now()->subDays(31),
    ]);

    expect((new ApproverResolver)->isAuthorized($request, $delegate))->toBeFalse();
});
