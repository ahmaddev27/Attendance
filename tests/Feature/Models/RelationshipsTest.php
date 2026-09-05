<?php

use App\Models\{Employee, Attendance, LeaveRequest, User};

it('employee has many attendances', function () {
    $employee = Employee::factory()->create();
    Attendance::factory()->for($employee)->count(3)->create();

    expect($employee->attendances)->toHaveCount(3);
});

it('employee has many leave requests', function () {
    $employee = Employee::factory()->create();
    LeaveRequest::factory()->for($employee)->count(2)->create();

    expect($employee->leaveRequests)->toHaveCount(2);
});

it('leave request belongs to reviewer user', function () {
    $reviewer = User::factory()->create();
    $leave = LeaveRequest::factory()->create(['reviewed_by' => $reviewer->id]);

    expect($leave->reviewer->id)->toBe($reviewer->id);
});
