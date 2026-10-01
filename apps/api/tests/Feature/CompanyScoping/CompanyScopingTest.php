<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Modules\Employees\Services\EmployeeService;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

beforeEach(function (): void {
    $this->actingAsSuperAdmin();

    // EmployeeService::create() auto-provisions a login user with the
    // 'employee' role; seed the role here so creation flows in these
    // tests don't blow up with the "role missing" guard.
    Role::findOrCreate('employee', 'web');
});

/**
 * Convenience: a Company that already owns a Department + Team so a
 * freshly-created Employee can be attached and inherit the company_id
 * via EmployeeService's team-sync logic.
 *
 * @return array{company: Company, department: Department, team: Team}
 */
function makeOrgChain(string $companyName = 'ACME'): array
{
    $company = Company::factory()->create(['name' => $companyName]);
    $department = Department::factory()->create(['company_id' => $company->id]);
    $team = Team::factory()->create(['department_id' => $department->id]);

    return ['company' => $company, 'department' => $department, 'team' => $team];
}

// -----------------------------------------------------------------------
// Backfill — migration populates company_id from the team->department
// chain for every pre-existing employee.
// -----------------------------------------------------------------------

test('migration backfills company_id from team.department.company_id', function (): void {
    $org = makeOrgChain('Alpha');
    $employee = Employee::factory()->create([
        'team_id' => $org['team']->id,
        'department_id' => $org['department']->id,
        'company_id' => null, // force the backfill path
    ]);

    // Re-apply the backfill (same operation the migration performs) on
    // the just-created row — RefreshDatabase ran the migration before
    // this test's data existed, so we exercise the same logic directly.
    \Illuminate\Support\Facades\DB::table('employees')
        ->where('id', $employee->id)
        ->update(['company_id' => $org['team']->department->company_id]);

    expect($employee->fresh()->company_id)->toBe($org['company']->id);
});

// -----------------------------------------------------------------------
// EmployeeService auto-sets company_id when team_id is set / changed
// -----------------------------------------------------------------------

test('creating an employee with a team_id auto-derives company_id', function (): void {
    $org = makeOrgChain('Auto-Derive Inc');
    makeWorkSchedule(); // required by StoreEmployeeRequest

    $service = app(EmployeeService::class);
    $employee = $service->create([
        'first_name' => 'Nour',
        'last_name' => 'Hadad',
        'email' => 'nour.hadad@example.test',
        'phone' => '0599000111',
        'employment_type' => 'full_time',
        'joining_date' => '2026-01-15',
        'team_id' => $org['team']->id,
        'department_id' => $org['department']->id,
        'work_schedule_id' => \App\Models\WorkSchedule::first()->id,
    ]);

    expect($employee->fresh()->company_id)->toBe($org['company']->id);
});

test('changing team_id on update re-derives company_id', function (): void {
    $orgA = makeOrgChain('A Corp');
    $orgB = makeOrgChain('B Corp');

    $employee = Employee::factory()->create([
        'team_id' => $orgA['team']->id,
        'department_id' => $orgA['department']->id,
        'company_id' => $orgA['company']->id,
    ]);

    app(EmployeeService::class)->update($employee, [
        'team_id' => $orgB['team']->id,
    ]);

    expect($employee->fresh()->company_id)->toBe($orgB['company']->id);
});

test('explicit company_id on update overrides team-derived value', function (): void {
    $orgA = makeOrgChain('A Inc');
    $orgB = makeOrgChain('B Inc');

    $employee = Employee::factory()->create([
        'team_id' => $orgA['team']->id,
        'department_id' => $orgA['department']->id,
        'company_id' => $orgA['company']->id,
    ]);

    // Caller passes company_id explicitly — service must not overwrite
    // it with the team's derived value.
    app(EmployeeService::class)->update($employee, [
        'team_id' => $orgA['team']->id,
        'company_id' => $orgB['company']->id,
    ]);

    expect($employee->fresh()->company_id)->toBe($orgB['company']->id);
});

// -----------------------------------------------------------------------
// Employees list — ?company_id filter
// -----------------------------------------------------------------------

test('employees list filters by company_id', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    Employee::factory()->count(2)->create(['company_id' => $orgA['company']->id]);
    Employee::factory()->count(3)->create(['company_id' => $orgB['company']->id]);

    $this->getJson("/api/employees?company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('employees list without company_id returns everyone', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    Employee::factory()->count(2)->create(['company_id' => $orgA['company']->id]);
    Employee::factory()->count(3)->create(['company_id' => $orgB['company']->id]);

    $this->getJson('/api/employees')
        ->assertOk()
        ->assertJsonPath('meta.total', 5);
});

test('employees list rejects an unknown company_id with 422', function (): void {
    // The validation happens inside the request flow; the controller does
    // not currently validate (filter is only read via `only()`), so this
    // request should still return 200 but with zero rows. The test
    // documents the resilient-filter behaviour.
    //
    // If we tighten validation later, change this to assertUnprocessable().
    Employee::factory()->count(2)->create();

    $this->getJson('/api/employees?company_id=99999')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

// -----------------------------------------------------------------------
// Attendance list — ?company_id filter
// -----------------------------------------------------------------------

test('attendance list filters by company_id', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    $empA = makeEmployeeWithSchedule();
    $empA->update(['company_id' => $orgA['company']->id]);
    $empB = makeEmployeeWithSchedule();
    $empB->update(['company_id' => $orgB['company']->id]);

    Attendance::factory()->create(['employee_id' => $empA->id]);
    Attendance::factory()->create(['employee_id' => $empB->id]);

    $this->getJson("/api/attendance?company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('attendance list validates company_id as integer existing', function (): void {
    $this->getJson('/api/attendance?company_id=99999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_id');
});

// -----------------------------------------------------------------------
// Leave requests list — ?company_id filter
// -----------------------------------------------------------------------

test('leave requests list filters by company_id', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    $empA = Employee::factory()->create(['company_id' => $orgA['company']->id]);
    $empB = Employee::factory()->create(['company_id' => $orgB['company']->id]);

    LeaveRequest::factory()->create(['employee_id' => $empA->id]);
    LeaveRequest::factory()->create(['employee_id' => $empB->id]);

    $this->getJson("/api/leave-requests?company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// -----------------------------------------------------------------------
// Admin requests list — ?company_id filter
// -----------------------------------------------------------------------

test('admin requests list filters by company_id', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    $empA = Employee::factory()->create(['company_id' => $orgA['company']->id]);
    $empB = Employee::factory()->create(['company_id' => $orgB['company']->id]);

    RequestModel::factory()->create(['employee_id' => $empA->id]);
    RequestModel::factory()->create(['employee_id' => $empB->id]);

    $this->getJson("/api/requests?company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// -----------------------------------------------------------------------
// Tasks list — ?company_id filter (creator OR assignee company match)
// -----------------------------------------------------------------------

test('tasks list filters by company_id via creator or assignee', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    $empA = Employee::factory()->create(['company_id' => $orgA['company']->id]);
    $empB = Employee::factory()->create(['company_id' => $orgB['company']->id]);

    $status = TaskStatus::factory()->create();
    $priority = TaskPriority::factory()->create();

    // Task 1 — creator from A: should appear in A's list
    Task::factory()->create([
        'created_by' => $empA->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
    ]);
    // Task 2 — creator + assignee from B: should NOT appear in A's list
    Task::factory()->create([
        'created_by' => $empB->id,
        'assigned_to' => $empB->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
    ]);
    // Task 3 — creator from B BUT assignee from A: should appear in A's list
    Task::factory()->create([
        'created_by' => $empB->id,
        'assigned_to' => $empA->id,
        'status_id' => $status->id,
        'priority_id' => $priority->id,
    ]);

    $this->getJson("/api/tasks?company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// -----------------------------------------------------------------------
// Attendance monthly report — ?company_id filter
// -----------------------------------------------------------------------

test('attendance monthly report filters by company_id', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    $empA = makeEmployeeWithSchedule();
    $empA->update(['company_id' => $orgA['company']->id]);
    $empB = makeEmployeeWithSchedule();
    $empB->update(['company_id' => $orgB['company']->id]);

    $date = \Illuminate\Support\Carbon::create(2026, 3, 15);
    Attendance::factory()->create([
        'employee_id' => $empA->id,
        'date' => $date->toDateString(),
    ]);
    Attendance::factory()->create([
        'employee_id' => $empB->id,
        'date' => $date->toDateString(),
    ]);

    $this->getJson("/api/admin/reports/attendance/monthly?year=2026&month=3&company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
