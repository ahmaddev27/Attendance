<?php

use App\Enums\LeaveStatus;
use App\Livewire\Admin\Leaves\LeaveList;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->app->instance(SmsGatewayInterface::class, new FakeSmsGateway());
});

it('lists leaves and filters by status', function () {
    $emp = Employee::factory()->create();
    LeaveRequest::factory()->for($emp)->count(2)->create(['status' => LeaveStatus::Pending]);
    LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Approved]);

    Livewire::test(LeaveList::class)
        ->set('status', 'pending')
        ->assertViewHas('leaves', fn ($items) => $items->count() === 2);
});

it('approves a leave', function () {
    $emp = Employee::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Pending]);

    Livewire::test(LeaveList::class)
        ->call('approve', $leave->id);

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved);
});

it('rejects a leave with reason', function () {
    $emp = Employee::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Pending]);

    Livewire::test(LeaveList::class)
        ->set('rejectingId', $leave->id)
        ->set('rejectReason', 'Not enough notice')
        ->call('confirmReject');

    expect($leave->fresh()->status)->toBe(LeaveStatus::Rejected);
    expect($leave->fresh()->rejection_reason)->toBe('Not enough notice');
});

it('shows error when trying to approve already-decided leave', function () {
    $emp = Employee::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Approved]);

    Livewire::test(LeaveList::class)
        ->call('approve', $leave->id)
        ->assertDispatched('toast', type: 'error');
});
