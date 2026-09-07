<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Shared\Enums\LeaveStatus;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ActsAsEmployeeUser;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(ActsAsEmployeeUser::class, CreatesSuperAdmin::class);

function makePendingLeaveRequest(array $overrides = []): LeaveRequest
{
    $leaveType = isset($overrides['leave_type_id'])
        ? LeaveType::query()->find($overrides['leave_type_id'])
        : LeaveType::factory()->create(['min_notice_days' => 0]);

    $employee = isset($overrides['employee_id'])
        ? Employee::query()->find($overrides['employee_id'])
        : makeEmployeeWithSchedule(makeWorkSchedule(['workdays' => [0, 1, 2, 3, 4, 5, 6]]));

    $defaultStart = Carbon::today()->addDays(10);

    $attributes = array_merge([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $defaultStart->toDateString(),
        'end_date' => $defaultStart->copy()->addDays(2)->toDateString(),
        'days' => 3.0,
        'status' => LeaveStatus::Pending,
    ], $overrides);

    $startYear = Carbon::parse($attributes['start_date'])->year;

    $balance = LeaveBalance::query()->firstOrCreate(
        ['employee_id' => $attributes['employee_id'], 'leave_type_id' => $attributes['leave_type_id'], 'year' => $startYear],
        ['entitlement' => 21],
    );
    $balance->increment('pending', $attributes['days']);

    return LeaveRequest::factory()->create($attributes);
}

test('an admin can approve a pending leave request and its balance moves from pending to used', function () {
    $this->actingAsSuperAdmin();
    $leaveRequest = makePendingLeaveRequest();

    $response = $this->postJson("/api/leave-requests/{$leaveRequest->id}/approve");

    $response->assertOk()->assertJsonPath('data.status', 'approved');

    $balance = LeaveBalance::query()
        ->where('employee_id', $leaveRequest->employee_id)
        ->where('leave_type_id', $leaveRequest->leave_type_id)
        ->where('year', $leaveRequest->start_date->year)
        ->firstOrFail();

    expect((float) $balance->pending)->toBe(0.0)
        ->and((float) $balance->used)->toBe((float) $leaveRequest->days);
});

test('an admin can reject a pending leave request with a reason and its balance releases the pending reservation', function () {
    $this->actingAsSuperAdmin();
    $leaveRequest = makePendingLeaveRequest();

    $response = $this->postJson("/api/leave-requests/{$leaveRequest->id}/reject", [
        'rejection_reason' => 'Insufficient staffing that week.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Insufficient staffing that week.');

    $balance = LeaveBalance::query()
        ->where('employee_id', $leaveRequest->employee_id)
        ->where('leave_type_id', $leaveRequest->leave_type_id)
        ->where('year', $leaveRequest->start_date->year)
        ->firstOrFail();

    expect((float) $balance->pending)->toBe(0.0)
        ->and((float) $balance->used)->toBe(0.0);
});

test('rejecting a leave request without a reason is rejected', function () {
    $this->actingAsSuperAdmin();
    $leaveRequest = makePendingLeaveRequest();

    $response = $this->postJson("/api/leave-requests/{$leaveRequest->id}/reject", []);

    $response->assertUnprocessable()->assertJsonValidationErrors(['rejection_reason']);
});

test('an already-decided leave request cannot be approved again', function () {
    $this->actingAsSuperAdmin();
    $leaveRequest = makePendingLeaveRequest(['status' => LeaveStatus::Approved]);

    $response = $this->postJson("/api/leave-requests/{$leaveRequest->id}/approve");

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

test('an already-decided leave request cannot be rejected', function () {
    $this->actingAsSuperAdmin();
    $leaveRequest = makePendingLeaveRequest(['status' => LeaveStatus::Rejected]);

    $response = $this->postJson("/api/leave-requests/{$leaveRequest->id}/reject", [
        'rejection_reason' => 'Too late now.',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

test('an employee can cancel their own pending leave request and its balance releases the pending reservation', function () {
    $leaveRequest = makePendingLeaveRequest();
    $this->actingAsEmployeeUser($leaveRequest->employee);

    $response = $this->postJson("/api/me/leaves/{$leaveRequest->id}/cancel");

    $response->assertOk()->assertJsonPath('data.status', 'cancelled');

    $balance = LeaveBalance::query()
        ->where('employee_id', $leaveRequest->employee_id)
        ->where('leave_type_id', $leaveRequest->leave_type_id)
        ->where('year', $leaveRequest->start_date->year)
        ->firstOrFail();

    expect((float) $balance->pending)->toBe(0.0);
});

test('an employee cannot cancel another employee\'s leave request', function () {
    $leaveRequest = makePendingLeaveRequest();
    $otherEmployee = makeEmployeeWithSchedule();
    $this->actingAsEmployeeUser($otherEmployee);

    $response = $this->postJson("/api/me/leaves/{$leaveRequest->id}/cancel");

    $response->assertForbidden();
});

test('an admin can cancel an approved leave request and its balance releases the used days', function () {
    $this->actingAsSuperAdmin();
    $leaveRequest = makePendingLeaveRequest(['status' => LeaveStatus::Approved]);

    // Simulate the approve step's bookkeeping (moved pending -> used) so
    // the cancel path has the right prior state to release from.
    $balance = LeaveBalance::query()
        ->where('employee_id', $leaveRequest->employee_id)
        ->where('leave_type_id', $leaveRequest->leave_type_id)
        ->where('year', $leaveRequest->start_date->year)
        ->firstOrFail();
    $balance->update(['pending' => 0, 'used' => $leaveRequest->days]);

    $response = $this->postJson("/api/leave-requests/{$leaveRequest->id}/cancel");

    $response->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect((float) $balance->refresh()->used)->toBe(0.0);
});

test('an employee cannot self-cancel an approved leave request that starts within the leave type notice window', function () {
    $leaveType = LeaveType::factory()->create(['min_notice_days' => 7]);
    $employee = makeEmployeeWithSchedule(makeWorkSchedule(['workdays' => [0, 1, 2, 3, 4, 5, 6]]));

    // Starts tomorrow — well inside the 7-day notice window.
    $leaveRequest = makePendingLeaveRequest([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => LeaveStatus::Approved,
        'start_date' => Carbon::today()->addDay()->toDateString(),
        'end_date' => Carbon::today()->addDay()->toDateString(),
    ]);

    $this->actingAsEmployeeUser($employee);

    $response = $this->postJson("/api/me/leaves/{$leaveRequest->id}/cancel");

    $response->assertUnprocessable();
});

test('a cancelled leave request cannot be cancelled again', function () {
    $leaveRequest = makePendingLeaveRequest(['status' => LeaveStatus::Cancelled]);
    $this->actingAsEmployeeUser($leaveRequest->employee);

    $response = $this->postJson("/api/me/leaves/{$leaveRequest->id}/cancel");

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});
