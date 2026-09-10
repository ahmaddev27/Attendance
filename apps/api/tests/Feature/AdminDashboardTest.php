<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\RequestType;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Shared\Enums\ApproverType;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\LeaveStatus;
use App\Shared\Enums\RequestStatus;

test('kpis endpoint requires authentication', function () {
    $this->getJson('/api/admin/dashboard/kpis')->assertUnauthorized();
});

test('kpis returns the expected top-level shape', function () {
    actingAsAdmin();

    $this->getJson('/api/admin/dashboard/kpis')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'employees' => ['total', 'active', 'inactive'],
                'today' => ['date', 'present', 'late', 'absent', 'on_leave'],
                'pending' => ['leaves', 'requests', 'total'],
                'tasks' => ['open', 'in_progress', 'overdue'],
            ],
        ]);
});

test('employee counts split active vs inactive', function () {
    actingAsAdmin();
    Employee::factory()->count(3)->create(['status' => EmployeeStatus::Active]);
    Employee::factory()->count(2)->create(['status' => EmployeeStatus::Inactive]);
    Employee::factory()->create(['status' => EmployeeStatus::OnLeave]);
    Employee::factory()->create(['status' => EmployeeStatus::Terminated]);

    $this->getJson('/api/admin/dashboard/kpis')
        ->assertJsonPath('data.employees.total', 7)
        ->assertJsonPath('data.employees.active', 3)
        ->assertJsonPath('data.employees.inactive', 4); // everything not 'active'
});

test('today attendance groups late+early_leave and folds remote/mission into present', function () {
    actingAsAdmin();
    $today = now()->toDateString();
    $employees = Employee::factory()->count(6)->create();

    Attendance::factory()->for($employees[0])->create(['date' => $today, 'status' => AttendanceStatus::Present]);
    Attendance::factory()->for($employees[1])->create(['date' => $today, 'status' => AttendanceStatus::Remote]);
    Attendance::factory()->for($employees[2])->create(['date' => $today, 'status' => AttendanceStatus::Late]);
    Attendance::factory()->for($employees[3])->create(['date' => $today, 'status' => AttendanceStatus::EarlyLeave]);
    Attendance::factory()->for($employees[4])->create(['date' => $today, 'status' => AttendanceStatus::Absent]);
    Attendance::factory()->for($employees[5])->create(['date' => $today, 'status' => AttendanceStatus::OnLeave]);

    $this->getJson('/api/admin/dashboard/kpis')
        ->assertJsonPath('data.today.present', 2)   // Present + Remote (business mission not created)
        ->assertJsonPath('data.today.late', 2)      // Late + EarlyLeave
        ->assertJsonPath('data.today.absent', 1)
        ->assertJsonPath('data.today.on_leave', 1);
});

test('pending totals sum pending leaves and pending requests', function () {
    actingAsAdmin();
    LeaveRequest::factory()->count(2)->create(['status' => LeaveStatus::Pending]);
    LeaveRequest::factory()->create(['status' => LeaveStatus::Approved]);

    $workflow = Workflow::factory()->create();
    WorkflowStep::factory()->for($workflow)->approverType(ApproverType::DepartmentManager)->create();
    $requestType = RequestType::factory()->for($workflow)->create();
    RequestModel::factory()->count(3)->create(['status' => RequestStatus::Pending, 'request_type_id' => $requestType->id]);
    RequestModel::factory()->create(['status' => RequestStatus::Approved, 'request_type_id' => $requestType->id]);

    $this->getJson('/api/admin/dashboard/kpis')
        ->assertJsonPath('data.pending.leaves', 2)
        ->assertJsonPath('data.pending.requests', 3)
        ->assertJsonPath('data.pending.total', 5);
});

test('task counts respect status flags and overdue date', function () {
    actingAsAdmin();
    $openStatus = TaskStatus::factory()->create(['is_done_state' => false, 'is_cancelled_state' => false]);
    $doneStatus = TaskStatus::factory()->doneState()->create();

    // 3 "not started" open tasks (no start_date, 0% progress).
    Task::factory()->count(3)->create(['status_id' => $openStatus->id, 'start_date' => null, 'progress_percent' => 0]);
    // 2 in-progress open tasks (started yesterday, progress > 0).
    // The dashboard now requires actual progress to count as
    // "in_progress" — a scheduled but untouched task is "open" only.
    Task::factory()->count(2)->create([
        'status_id' => $openStatus->id,
        'start_date' => now()->subDay()->toDateString(),
        'progress_percent' => 40,
    ]);
    // 1 overdue open task (past due, no completed_at, 0% progress).
    Task::factory()->create([
        'status_id' => $openStatus->id,
        'progress_percent' => 0,
        'due_date' => now()->subDays(3)->toDateString(),
    ]);
    // A done task should NOT count in any bucket (both signals set —
    // status is done AND completed_at is stamped).
    Task::factory()->create([
        'status_id' => $doneStatus->id,
        'start_date' => now()->subDay()->toDateString(),
        'due_date' => now()->subDays(3)->toDateString(),
        'completed_at' => now()->subDay(),
    ]);

    $this->getJson('/api/admin/dashboard/kpis')
        ->assertJsonPath('data.tasks.open', 6)          // 3 + 2 + 1 open
        ->assertJsonPath('data.tasks.in_progress', 2)   // 2 with progress > 0
        ->assertJsonPath('data.tasks.overdue', 1);
});
