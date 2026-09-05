<?php

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveService;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;

beforeEach(function () {
    $this->fake = new FakeSmsGateway();
    $this->app->instance(SmsGatewayInterface::class, $this->fake);
});

it('creates a pending leave request', function () {
    $emp = Employee::factory()->create();

    $leave = app(LeaveService::class)->submit($emp, [
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-01',
        'note' => 'family',
    ]);

    expect($leave->status)->toBe(LeaveStatus::Pending);
    expect($leave->employee_id)->toBe($emp->id);
});

it('approves a request and sends SMS', function () {
    $emp = Employee::factory()->create();
    $reviewer = User::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['start_date' => '2026-10-01', 'end_date' => '2026-10-01']);

    app(LeaveService::class)->approve($leave, $reviewer);

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved);
});

it('rejects a request with a reason and sends SMS', function () {
    $emp = Employee::factory()->create();
    $reviewer = User::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create();

    app(LeaveService::class)->reject($leave, $reviewer, 'Too short notice');

    expect($leave->fresh()->status)->toBe(LeaveStatus::Rejected);
    expect($leave->fresh()->rejection_reason)->toBe('Too short notice');
});

it('does not re-review already-decided requests', function () {
    $emp = Employee::factory()->create();
    $reviewer = User::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Approved]);

    expect(fn () => app(LeaveService::class)->reject($leave, $reviewer, 'x'))
        ->toThrow(\DomainException::class);
});

it('prevents overlapping leave requests', function () {
    $emp = Employee::factory()->create();
    LeaveRequest::factory()->for($emp)->create([
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-10',
        'status' => LeaveStatus::Pending,
    ]);

    expect(fn () => app(LeaveService::class)->submit($emp, [
        'start_date' => '2026-10-07',
        'end_date' => '2026-10-08',
    ]))->toThrow(\DomainException::class);
});
