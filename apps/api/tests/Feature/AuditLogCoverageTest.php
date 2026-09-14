<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeScanPin;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Request as RequestModel;
use App\Models\RequestApproval;
use App\Models\RequestType;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkSchedule;
use App\Shared\Enums\ApproverType;
use App\Shared\Enums\LeaveStatus;
use App\Shared\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\ActsAsEmployeeUser;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(ActsAsEmployeeUser::class, CreatesSuperAdmin::class);

/**
 * @return Collection<int, ActivityLog>
 */
function auditTrailFor(Model $subject, string $logName): Collection
{
    return ActivityLog::query()
        ->where('log_name', $logName)
        ->where('subject_type', $subject->getMorphClass())
        ->where('subject_id', $subject->getKey())
        ->orderBy('id')
        ->get();
}

/**
 * Searches every property bag in the table, so a secret leaking through any
 * channel fails the test, not only through the subject under test.
 */
function allAuditPropertiesAsJson(): string
{
    return (string) json_encode(
        ActivityLog::query()->get()->map(fn (ActivityLog $entry) => $entry->properties?->toArray()),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );
}

function auditPendingLeaveRequest(): LeaveRequest
{
    $leaveType = LeaveType::factory()->create(['min_notice_days' => 0, 'is_balance_based' => true]);
    $employee = makeEmployeeWithSchedule(makeWorkSchedule(['workdays' => [0, 1, 2, 3, 4, 5, 6]]));
    $start = Carbon::today()->addDays(10);

    LeaveBalance::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => $start->year,
        'entitlement' => 21,
        'pending' => 3,
    ]);

    return LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $start->copy()->addDays(2)->toDateString(),
        'days' => 3,
        'status' => LeaveStatus::Pending,
    ]);
}

test('employee create, update and delete are logged with the acting admin and only whitelisted attributes', function () {
    $admin = $this->actingAsSuperAdmin();
    Role::findOrCreate('employee', 'web');

    $employeeId = $this->postJson('/api/employees', [
        'first_name' => 'Layla',
        'last_name' => 'Nassar',
        'email' => 'layla.audit@taqat.local',
        'employment_type' => 'full_time',
        'joining_date' => '2026-01-15',
        'birth_date' => '1992-04-01',
        'notes' => 'ملاحظة داخلية',
        'work_schedule_id' => WorkSchedule::factory()->create()->id,
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/employees/{$employeeId}", ['first_name' => 'Rania'])->assertOk();
    $this->deleteJson("/api/employees/{$employeeId}")->assertOk();

    $trail = auditTrailFor(Employee::withTrashed()->findOrFail($employeeId), 'employees');
    $audited = [
        'employee_number', 'first_name', 'last_name', 'email', 'phone', 'position_id', 'department_id',
        'team_id', 'direct_manager_id', 'employment_type', 'joining_date', 'status', 'work_schedule_id',
    ];

    expect($trail->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($trail->pluck('causer_id')->unique()->values()->all())->toEqual([$admin->id])
        ->and($trail->pluck('causer_type')->unique()->values()->all())->toBe([User::class])
        ->and(array_keys($trail[0]->properties['attributes']))->toEqualCanonicalizing($audited)
        ->and($trail[1]->properties->toArray())->toEqual([
            'attributes' => ['first_name' => 'Rania'],
            'old' => ['first_name' => 'Layla'],
        ])
        ->and(array_keys($trail[2]->properties['old']))->toEqualCanonicalizing($audited);
});

test('approving a leave request logs the decision and the balance moving from pending to used', function () {
    $admin = $this->actingAsSuperAdmin();
    $leaveRequest = auditPendingLeaveRequest();
    $balance = LeaveBalance::query()->where('employee_id', $leaveRequest->employee_id)->sole();

    $this->postJson("/api/leave-requests/{$leaveRequest->id}/approve")->assertOk();

    $decision = auditTrailFor($leaveRequest, 'leaves')->where('event', 'updated')->sole();
    $balanceMoves = auditTrailFor($balance, 'leaves')->where('event', 'updated')->values();

    expect($decision->causer_id)->toEqual($admin->id)
        ->and($decision->properties['old']['status'])->toBe('pending')
        ->and($decision->properties['attributes']['status'])->toBe('approved')
        ->and($decision->properties['attributes']['reviewed_by'])->toEqual($admin->id)
        ->and($balanceMoves)->toHaveCount(2)
        ->and($balanceMoves->pluck('causer_id')->unique()->values()->all())->toEqual([$admin->id])
        ->and((float) $balanceMoves[0]->properties['attributes']['pending'])->toBe(0.0)
        ->and((float) $balanceMoves[1]->properties['attributes']['used'])->toBe(3.0);
});

test('rejecting a leave request logs the decision with its reason', function () {
    $admin = $this->actingAsSuperAdmin();
    $leaveRequest = auditPendingLeaveRequest();

    $this->postJson("/api/leave-requests/{$leaveRequest->id}/reject", [
        'rejection_reason' => 'ضغط عمل في ذلك الأسبوع',
    ])->assertOk();

    $decision = auditTrailFor($leaveRequest, 'leaves')->where('event', 'updated')->sole();

    expect($decision->causer_id)->toEqual($admin->id)
        ->and($decision->properties['old']['status'])->toBe('pending')
        ->and($decision->properties['attributes'])->toMatchArray([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'rejection_reason' => 'ضغط عمل في ذلك الأسبوع',
        ]);
});

test('an admin leave balance adjustment logs the entitlement change', function () {
    $admin = $this->actingAsSuperAdmin();
    $leaveType = LeaveType::factory()->create(['default_annual_entitlement' => 21]);
    $employee = Employee::factory()->create();

    $this->postJson('/api/leave-balances/adjust', [
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => (int) now()->year,
        'delta' => 3,
        'reason' => 'تعويض عن عطلة رسمية',
    ])->assertOk();

    $balance = LeaveBalance::query()->where('employee_id', $employee->id)->sole();
    $change = auditTrailFor($balance, 'leaves')->where('event', 'updated')->sole();

    expect($change->causer_id)->toEqual($admin->id)
        ->and((float) $change->properties['old']['entitlement'])->toBe(21.0)
        ->and((float) $change->properties['attributes']['entitlement'])->toBe(24.0);
});

test('an approver decision on a request is logged with the approver as causer', function () {
    $submitter = Employee::factory()->create();
    $approver = Employee::factory()->create();
    $workflow = Workflow::factory()->create();
    $step = WorkflowStep::factory()->for($workflow)->order(1)
        ->approverType(ApproverType::SpecificEmployee, (string) $approver->id)
        ->create();
    $request = RequestModel::factory()->create([
        'employee_id' => $submitter->id,
        'request_type_id' => RequestType::factory()->for($workflow)->create()->id,
        'status' => RequestStatus::Pending,
        'current_step_id' => $step->id,
    ]);

    $approverUser = $this->actingAsEmployeeUser($approver);

    $this->postJson("/api/requests/{$request->id}/approve")->assertOk();

    $decision = ActivityLog::query()
        ->where('log_name', 'requests')
        ->where('subject_type', RequestApproval::class)
        ->sole();
    $statusChange = auditTrailFor($request, 'requests')->where('event', 'updated')->sole();

    expect($decision->event)->toBe('created')
        ->and($decision->causer_id)->toEqual($approverUser->id)
        ->and($decision->properties['attributes'])->toMatchArray([
            'request_id' => $request->id,
            'workflow_step_id' => $step->id,
            'approver_id' => $approver->id,
            'action' => 'approved',
        ])
        ->and($statusChange->causer_id)->toEqual($approverUser->id)
        ->and($statusChange->properties['attributes']['status'])->toBe('approved');
});

test('a settings change is logged by the settings screen only, never with the stored value', function () {
    $this->actingAsSuperAdmin();
    $secret = 'mtc-S3cret-Value-8841';

    $this->putJson('/api/admin/settings', ['sms' => ['mtc_password' => $secret, 'mtc_username' => 'taqat-sms']])->assertOk();
    $this->putJson('/api/admin/settings', ['sms' => ['mtc_password' => "{$secret}-rotated"]])->assertOk();

    $setting = Setting::query()->where('key', 'sms.mtc_password')->sole();
    $leakCheck = allAuditPropertiesAsJson();

    // SettingsController already writes one entry per saved key, so a
    // model-level trail on Setting would only duplicate every change.
    expect(ActivityLog::query()->where('log_name', 'settings')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('subject_type', Setting::class)->exists())->toBeFalse()
        ->and($leakCheck)->not->toContain($secret)
        ->and($leakCheck)->not->toContain((string) $setting->value);
});

test('user password and remember token changes never reach the audit trail', function () {
    $this->actingAsSuperAdmin();
    $user = User::factory()->create(['name' => 'Old Name']);

    $user->forceFill(['password' => 'Rotated-Password-2026'])->save();

    expect(auditTrailFor($user, 'users')->pluck('event')->all())->toBe(['created']);

    $user->forceFill([
        'name' => 'New Name',
        'password' => 'Second-Rotation-2026',
        'remember_token' => 'fresh-remember-token',
    ])->save();

    $trail = auditTrailFor($user, 'users');
    $leakCheck = allAuditPropertiesAsJson();

    expect($trail->pluck('event')->all())->toBe(['created', 'updated'])
        ->and(array_keys($trail[0]->properties['attributes']))
        ->toEqualCanonicalizing(['name', 'email', 'employee_number', 'is_active', 'employee_id'])
        ->and($trail[1]->properties->toArray())->toEqual([
            'attributes' => ['name' => 'New Name'],
            'old' => ['name' => 'Old Name'],
        ])
        ->and($leakCheck)->not->toContain('password')
        ->and($leakCheck)->not->toContain('remember_token')
        ->and($leakCheck)->not->toContain((string) $user->fresh()->password)
        ->and($leakCheck)->not->toContain('fresh-remember-token');
});

test('scan PIN writes never put the PIN hash into the audit trail', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create(['phone' => null]);

    issueScanPinFor($employee, '4812');
    $issuedHash = (string) EmployeeScanPin::query()->where('employee_id', $employee->id)->value('pin_hash');

    $this->postJson("/api/employees/{$employee->id}/scan-pin")->assertOk();

    $scanPin = EmployeeScanPin::query()->where('employee_id', $employee->id)->sole();
    $leakCheck = allAuditPropertiesAsJson();

    expect(auditTrailFor($employee, 'attendance')->pluck('description')->all())->toBe(['scan_pin_reset'])
        ->and($leakCheck)->not->toContain('pin_hash')
        ->and($leakCheck)->not->toContain($issuedHash)
        ->and($leakCheck)->not->toContain($scanPin->pin_hash);
});

test('token sign-in and sign-out are logged with the caller ip', function () {
    $user = User::factory()->create(['employee_number' => 4321, 'password' => bcrypt('secret-password')]);

    $token = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->postJson('/api/auth/login', ['employee_number' => 4321, 'password' => 'secret-password'])
        ->assertOk()
        ->json('token');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
        ->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk();

    $entries = ActivityLog::query()->where('log_name', 'auth')->orderBy('id')->get();

    expect($entries->map(fn (ActivityLog $entry) => [
        $entry->description,
        $entry->causer_id,
        $entry->properties['ip'],
    ])->all())->toEqual([
        ['login', $user->id, '203.0.113.7'],
        ['logout', $user->id, '203.0.113.8'],
    ]);
});

test('a session sign-in and sign-out through the web guard are logged', function () {
    $user = User::factory()->create();

    Auth::guard('web')->login($user);
    Auth::guard('web')->logout();

    $entries = ActivityLog::query()->where('log_name', 'auth')->orderBy('id')->get();

    expect($entries->pluck('description')->all())->toBe(['login', 'logout'])
        ->and($entries->pluck('causer_id')->unique()->values()->all())->toEqual([$user->id])
        ->and($entries->every(fn (ActivityLog $entry) => array_key_exists('ip', $entry->properties->toArray())))->toBeTrue();
});

test('assigning and removing a role is logged against the user', function () {
    $admin = $this->actingAsSuperAdmin();
    $role = Role::findOrCreate('employee', 'web');
    $user = User::factory()->create();

    $user->assignRole($role);
    $user->removeRole($role);

    $entries = auditTrailFor($user, 'users')->whereIn('description', ['roles_assigned', 'roles_removed'])->values();

    expect($entries->pluck('description')->all())->toBe(['roles_assigned', 'roles_removed'])
        ->and($entries->pluck('causer_id')->unique()->values()->all())->toEqual([$admin->id])
        ->and($entries->map(fn (ActivityLog $entry) => $entry->properties['role_ids'])->all())
        ->toEqual([[$role->id], [$role->id]]);
});

test('the audit log endpoint filters the new entries by log_name and subject_type', function () {
    $this->actingAsSuperAdmin();
    $department = Department::factory()->create();
    Employee::factory()->create();

    $this->getJson('/api/admin/audit-log?log_name=organization')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.log_name', 'organization')
        ->assertJsonPath('data.0.event', 'created')
        ->assertJsonPath('data.0.subject_type', 'Department')
        ->assertJsonPath('data.0.subject_id', $department->id);

    $this->getJson('/api/admin/audit-log?'.http_build_query(['subject_type' => Employee::class]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.log_name', 'employees');
});
