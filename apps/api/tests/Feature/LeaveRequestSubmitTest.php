<?php

use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkSchedule;
use App\Shared\Enums\HolidayType;
use App\Shared\Enums\LeaveStatus;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ActsAsEmployeeUser;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(ActsAsEmployeeUser::class, CreatesSuperAdmin::class);

/**
 * Every day is a workday for this schedule, so `days` always equals the
 * plain calendar span — tests that aren't specifically about
 * weekend/holiday skipping don't need to reason about which day of the
 * week "today" happens to be.
 */
function allDaysWorkSchedule(): WorkSchedule
{
    return makeWorkSchedule(['workdays' => [0, 1, 2, 3, 4, 5, 6]]);
}

test('an employee can submit a valid leave request and it reserves the balance as pending', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0, 'default_annual_entitlement' => 21]);

    $start = Carbon::today()->addDays(10);
    $end = $start->copy()->addDays(2); // 3 calendar days

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
        'reason' => 'Family trip',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.days', 3);

    $balance = LeaveBalance::query()
        ->where('employee_id', $employee->id)
        ->where('leave_type_id', $leaveType->id)
        ->where('year', $start->year)
        ->firstOrFail();

    expect((float) $balance->pending)->toBe(3.0)
        ->and((float) $balance->used)->toBe(0.0);
});

test('submitting an overlapping leave request is rejected', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0]);

    $existingStart = Carbon::today()->addDays(10);
    $existingEnd = $existingStart->copy()->addDays(4);

    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $existingStart->toDateString(),
        'end_date' => $existingEnd->toDateString(),
        'status' => LeaveStatus::Pending,
    ]);

    // Overlaps the middle of the existing request.
    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $existingStart->copy()->addDay()->toDateString(),
        'end_date' => $existingEnd->copy()->addDay()->toDateString(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
});

test('submitting without enough remaining balance is rejected when negative balance is not allowed', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create([
        'min_notice_days' => 0,
        'allow_negative_balance' => false,
        'default_annual_entitlement' => 2, // fewer days than requested below
    ]);

    $start = Carbon::today()->addDays(10);
    $end = $start->copy()->addDays(4); // 5 calendar days requested, only 2 available

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['days']);
});

test('submitting a leave request that allows a negative balance is not blocked by insufficient balance', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create([
        'min_notice_days' => 0,
        'allow_negative_balance' => true,
        'default_annual_entitlement' => 0,
    ]);

    $start = Carbon::today()->addDays(10);
    $end = $start->copy()->addDays(4);

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
    ]);

    $response->assertCreated();
});

test('submitting a leave request that requires an attachment without one is rejected', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0, 'requires_attachment' => true]);

    $start = Carbon::today()->addDays(10);

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->addDay()->toDateString(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['attachment_path']);
});

test('submitting with an attachment when one is required succeeds', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $user = $employee->user;
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0, 'requires_attachment' => true]);

    // The attachment_path rule (SubmitLeaveRequestRequest) enforces:
    //   1. prefix must be "leave-attachments/{caller.user.id}/"
    //   2. no path traversal
    //   3. file must exist on the `local` disk
    // Simulate a legitimate prior upload by materialising the file.
    $path = "leave-attachments/{$user->id}/medical-note.pdf";
    \Illuminate\Support\Facades\Storage::disk('local')->put($path, 'fake pdf bytes');

    $start = Carbon::today()->addDays(10);

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->addDay()->toDateString(),
        'attachment_path' => $path,
    ]);

    $response->assertCreated();
});

test('computed days skip weekends', function () {
    $schedule = makeWorkSchedule(['workdays' => [1, 2, 3, 4, 5]]); // Mon-Fri
    $employee = makeEmployeeWithSchedule($schedule);
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0]);

    $start = Carbon::parse('next monday');
    $end = $start->copy()->addDays(6); // full week: 5 workdays + 2 weekend days

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
    ]);

    $response->assertCreated()->assertJsonPath('data.days', 5);
});

test('computed days skip holidays', function () {
    $schedule = makeWorkSchedule(['workdays' => [1, 2, 3, 4, 5]]); // Mon-Fri
    $employee = makeEmployeeWithSchedule($schedule);
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0]);

    $start = Carbon::parse('next monday');
    $end = $start->copy()->addDays(4); // Mon-Fri, all workdays

    Holiday::factory()->create([
        'date' => $start->copy()->addDays(2)->toDateString(), // the Wednesday
        'type' => HolidayType::Official,
        'is_recurring' => false,
    ]);

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
    ]);

    $response->assertCreated()->assertJsonPath('data.days', 4);
});

test('submitting before the leave type minimum notice period is rejected', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 7]);

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => Carbon::today()->addDay()->toDateString(),
        'end_date' => Carbon::today()->addDays(2)->toDateString(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
});

test('submitting beyond the leave type max consecutive days is rejected', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0, 'max_consecutive_days' => 3]);

    $start = Carbon::today()->addDays(10);
    $end = $start->copy()->addDays(4); // 5 consecutive calendar days > 3 allowed

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
});

test('an admin can submit a leave request on behalf of an employee', function () {
    $this->actingAsSuperAdmin();

    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0]);

    $start = Carbon::today()->addDays(10);

    $response = $this->postJson('/api/leave-requests', [
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->addDay()->toDateString(),
    ]);

    $response->assertCreated()->assertJsonPath('data.employee_id', $employee->id);
    $this->assertDatabaseHas('leave_requests', ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id]);
});

test('an admin submitting without employee_id is rejected', function () {
    $this->actingAsSuperAdmin();

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0]);

    $response = $this->postJson('/api/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => Carbon::today()->addDays(10)->toDateString(),
        'end_date' => Carbon::today()->addDays(11)->toDateString(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['employee_id']);
});

test('submitting for an inactive leave type is rejected', function () {
    $employee = makeEmployeeWithSchedule(allDaysWorkSchedule());
    $this->actingAsEmployeeUser($employee);

    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0, 'is_active' => false]);

    $response = $this->postJson('/api/me/leaves', [
        'leave_type_id' => $leaveType->id,
        'start_date' => Carbon::today()->addDays(10)->toDateString(),
        'end_date' => Carbon::today()->addDays(11)->toDateString(),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['leave_type_id']);
});
