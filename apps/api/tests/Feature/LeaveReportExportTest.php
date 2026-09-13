<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Shared\Enums\LeaveStatus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\CreatesSuperAdmin;
use Tests\Feature\Concerns\ReadsCsvExports;

uses(CreatesSuperAdmin::class, ReadsCsvExports::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function makeLeaveForReport(array $attributes = []): LeaveRequest
{
    $year = now()->year;

    return LeaveRequest::factory()->create([
        'start_date' => "{$year}-03-01",
        'end_date' => "{$year}-03-02",
        ...$attributes,
    ]);
}

/**
 * @return list<string>
 */
function reportLeaveIds(LeaveRequest ...$leaves): array
{
    return array_map(fn (LeaveRequest $leave): string => (string) $leave->id, $leaves);
}

beforeEach(function (): void {
    $this->actingAsSuperAdmin();
});

test('leave export streams a BOM-prefixed CSV with the Arabic header and one row per leave', function () {
    $year = now()->year;
    $department = Department::factory()->create(['name' => 'المالية']);
    $employee = Employee::factory()->create([
        'first_name' => 'سارة',
        'last_name' => 'الحداد',
        'department_id' => $department->id,
    ]);
    $leaveType = LeaveType::factory()->create(['name' => 'سنوية']);
    $reviewer = User::factory()->create(['name' => 'مدير الموارد']);

    $approved = makeLeaveForReport([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => "{$year}-03-01",
        'end_date' => "{$year}-03-03",
        'days' => 2.5,
        'status' => LeaveStatus::Approved,
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => "{$year}-02-20 09:30:00",
    ]);
    makeLeaveForReport(['start_date' => "{$year}-05-10", 'end_date' => "{$year}-05-10"]);

    $response = $this->get('/api/admin/reports/leaves/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toBe("attachment; filename=\"leaves-{$year}.csv\"");

    $rows = $this->csvRows($response);

    expect($rows)->toHaveCount(3);
    expect($rows[0])->toBe([
        'رقم الطلب',
        'رقم الموظف',
        'اسم الموظف',
        'القسم',
        'نوع الإجازة',
        'تاريخ البداية',
        'تاريخ النهاية',
        'عدد الأيام',
        'الحالة',
        'تاريخ التقديم',
        'تاريخ القرار',
        'صاحب القرار',
    ]);
    expect($rows[1])->toBe([
        (string) $approved->id,
        (string) $employee->employee_number,
        'سارة الحداد',
        'المالية',
        'سنوية',
        "{$year}-03-01",
        "{$year}-03-03",
        '2.5',
        'موافق عليها',
        $approved->created_at->format('Y-m-d H:i'),
        "{$year}-02-20 09:30",
        'مدير الموارد',
    ]);
});

test('year keeps every leave overlapping it and defaults to the current year', function () {
    $year = now()->year;
    $previousYear = $year - 1;
    $nextYear = $year + 1;

    $previousOnly = makeLeaveForReport(['start_date' => "{$previousYear}-06-01", 'end_date' => "{$previousYear}-06-02"]);
    $spanningNewYear = makeLeaveForReport(['start_date' => "{$previousYear}-12-30", 'end_date' => "{$year}-01-02"]);
    $midYear = makeLeaveForReport(['start_date' => "{$year}-07-01", 'end_date' => "{$year}-07-01"]);
    $lastDayOfYear = makeLeaveForReport(['start_date' => "{$year}-12-31", 'end_date' => "{$nextYear}-01-01"]);
    makeLeaveForReport(['start_date' => "{$nextYear}-02-01", 'end_date' => "{$nextYear}-02-01"]);

    expect($this->csvColumn($this->get('/api/admin/reports/leaves/export'), 0))
        ->toBe(reportLeaveIds($spanningNewYear, $midYear, $lastDayOfYear));

    $previousYearResponse = $this->get("/api/admin/reports/leaves/export?year={$previousYear}");

    expect($previousYearResponse->headers->get('Content-Disposition'))->toContain("leaves-{$previousYear}.csv");
    expect($this->csvColumn($previousYearResponse, 0))->toBe(reportLeaveIds($previousOnly, $spanningNewYear));
});

test('leave_type_id narrows the leave export', function () {
    $leaveType = LeaveType::factory()->create();
    $matching = makeLeaveForReport(['leave_type_id' => $leaveType->id]);
    makeLeaveForReport();

    expect($this->csvColumn($this->get("/api/admin/reports/leaves/export?leave_type_id={$leaveType->id}"), 0))
        ->toBe(reportLeaveIds($matching));
});

test('status narrows the leave export', function () {
    $rejected = makeLeaveForReport(['status' => LeaveStatus::Rejected]);
    makeLeaveForReport(['status' => LeaveStatus::Pending]);

    expect($this->csvColumn($this->get('/api/admin/reports/leaves/export?status=rejected'), 0))
        ->toBe(reportLeaveIds($rejected));
});

test('department_id narrows the leave export and still names former employees', function () {
    $department = Department::factory()->create();
    $current = makeLeaveForReport([
        'employee_id' => Employee::factory()->create(['department_id' => $department->id])->id,
    ]);
    $formerEmployee = Employee::factory()->create([
        'first_name' => 'Former',
        'last_name' => 'Staff',
        'department_id' => $department->id,
    ]);
    $former = makeLeaveForReport(['employee_id' => $formerEmployee->id]);
    $formerEmployee->delete();
    makeLeaveForReport();

    $rows = array_slice($this->csvRows($this->get("/api/admin/reports/leaves/export?department_id={$department->id}")), 1);

    expect(array_column($rows, 0))->toBe(reportLeaveIds($current, $former));
    expect($rows[1][2])->toBe('Former Staff');
});

test('leave export rejects filters outside the allowed values', function () {
    $this->getJson('/api/admin/reports/leaves/export?status=archived&year=1999&department_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status', 'year', 'department_id']);
});

test('a user without view-reports is forbidden from the leave export', function () {
    $approver = User::factory()->create();
    $approver->givePermissionTo('approve-leaves');
    Sanctum::actingAs($approver);

    $this->getJson('/api/admin/reports/leaves/export')->assertForbidden();
});

test('a leave cell starting with = gets a leading apostrophe while numbers stay numbers', function () {
    $employee = Employee::factory()->create(['first_name' => '=HYPERLINK("https://evil.test")', 'last_name' => 'X']);
    $leave = makeLeaveForReport(['employee_id' => $employee->id]);

    $response = $this->get('/api/admin/reports/leaves/export');

    expect($this->csvRows($response)[1][2])->toBe('\'=HYPERLINK("https://evil.test") X');
    expect($response->streamedContent())->toContain("\n{$leave->id},{$employee->employee_number},");
});

test('the leave export runs the same number of queries for 3 rows as for 60', function () {
    $department = Department::factory()->create();
    $createLeaves = fn (int $count) => LeaveRequest::factory()->count($count)->create([
        'employee_id' => Employee::factory()->state(['department_id' => $department->id]),
        'reviewed_by' => User::factory(),
        'reviewed_at' => now(),
        'status' => LeaveStatus::Approved,
        'start_date' => now()->year.'-04-01',
        'end_date' => now()->year.'-04-02',
    ]);
    $countStreamingQueries = function (): int {
        $response = $this->get('/api/admin/reports/leaves/export');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response->streamedContent();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $createLeaves(3);
    $queriesForThreeRows = $countStreamingQueries();

    $createLeaves(57);
    $queriesForSixtyRows = $countStreamingQueries();

    expect($queriesForSixtyRows)->toBe($queriesForThreeRows)->toBeLessThanOrEqual(5);
    expect($this->csvRows($this->get('/api/admin/reports/leaves/export')))->toHaveCount(61);
});
