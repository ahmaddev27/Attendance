<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\RequestType;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Shared\Enums\RequestStatus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\CreatesSuperAdmin;
use Tests\Feature\Concerns\ReadsCsvExports;

uses(CreatesSuperAdmin::class, ReadsCsvExports::class);

/**
 * @return list<string>
 */
function reportRequestNumbers(RequestModel ...$requests): array
{
    return array_map(fn (RequestModel $request): string => $request->request_number, $requests);
}

beforeEach(function (): void {
    $this->actingAsSuperAdmin();
});

test('request export streams a BOM-prefixed CSV with the Arabic header and one row per request', function () {
    $this->freezeTime();

    $department = Department::factory()->create(['name' => 'العمليات']);
    $employee = Employee::factory()->create([
        'first_name' => 'عمر',
        'last_name' => 'خليل',
        'department_id' => $department->id,
    ]);
    $requestType = RequestType::factory()->create(['name' => 'شهادة راتب']);
    $step = WorkflowStep::factory()->create(['workflow_id' => $requestType->workflow_id, 'name' => 'اعتماد المدير']);

    $pending = RequestModel::factory()->create([
        'request_number' => 'REQ-2026-0001',
        'employee_id' => $employee->id,
        'request_type_id' => $requestType->id,
        'status' => RequestStatus::Pending,
        'current_step_id' => $step->id,
        'submitted_at' => '2026-09-01 08:15:00',
    ]);
    RequestModel::factory()->create([
        'status' => RequestStatus::Approved,
        'submitted_at' => '2026-09-02 10:00:00',
        'completed_at' => '2026-09-03 12:45:00',
    ]);

    $response = $this->get('/api/admin/reports/requests/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))
        ->toBe('attachment; filename="requests-'.now()->format('Ymd').'.csv"');

    $rows = $this->csvRows($response);

    expect($rows)->toHaveCount(3);
    expect($rows[0])->toBe([
        'رقم الطلب',
        'نوع الطلب',
        'رقم الموظف',
        'اسم الموظف',
        'القسم',
        'الحالة',
        'الخطوة الحالية',
        'تاريخ الإرسال',
        'تاريخ الإغلاق',
    ]);
    expect($rows[1])->toBe([
        $pending->request_number,
        'شهادة راتب',
        (string) $employee->employee_number,
        'عمر خليل',
        'العمليات',
        'قيد المراجعة',
        'اعتماد المدير',
        '2026-09-01 08:15',
        '',
    ]);
    expect($rows[2][5])->toBe('موافق عليه');
    expect($rows[2][8])->toBe('2026-09-03 12:45');
});

test('request_type_id narrows the request export', function () {
    $requestType = RequestType::factory()->create();
    $matching = RequestModel::factory()->create(['request_type_id' => $requestType->id]);
    RequestModel::factory()->create();

    expect($this->csvColumn($this->get("/api/admin/reports/requests/export?request_type_id={$requestType->id}"), 0))
        ->toBe(reportRequestNumbers($matching));
});

test('status narrows the request export', function () {
    $returned = RequestModel::factory()->create(['status' => RequestStatus::Returned]);
    RequestModel::factory()->create(['status' => RequestStatus::Pending]);

    expect($this->csvColumn($this->get('/api/admin/reports/requests/export?status=returned'), 0))
        ->toBe(reportRequestNumbers($returned));
});

test('department_id narrows the request export', function () {
    $department = Department::factory()->create();
    $matching = RequestModel::factory()->create([
        'employee_id' => Employee::factory()->create(['department_id' => $department->id])->id,
    ]);
    RequestModel::factory()->create();

    expect($this->csvColumn($this->get("/api/admin/reports/requests/export?department_id={$department->id}"), 0))
        ->toBe(reportRequestNumbers($matching));
});

test('from and to bound submitted_at inclusively by calendar day', function () {
    RequestModel::factory()->create(['submitted_at' => '2026-08-31 23:59:59']);
    $firstSecond = RequestModel::factory()->create(['submitted_at' => '2026-09-01 00:00:00']);
    $lastSecond = RequestModel::factory()->create(['submitted_at' => '2026-09-10 23:59:59']);
    RequestModel::factory()->create(['submitted_at' => '2026-09-11 00:00:00']);
    RequestModel::factory()->create(['submitted_at' => null, 'status' => RequestStatus::Draft]);

    expect($this->csvColumn($this->get('/api/admin/reports/requests/export?from=2026-09-01&to=2026-09-10'), 0))
        ->toBe(reportRequestNumbers($firstSecond, $lastSecond));
});

test('request export rejects a reversed or malformed date range', function () {
    $this->getJson('/api/admin/reports/requests/export?from=2026-09-10&to=2026-09-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to']);

    $this->getJson('/api/admin/reports/requests/export?from=10/09/2026&status=archived')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from', 'status']);
});

test('a user without view-reports is forbidden from the request export', function () {
    $workflowAdmin = User::factory()->create();
    $workflowAdmin->givePermissionTo('manage-workflows');
    Sanctum::actingAs($workflowAdmin);

    $this->getJson('/api/admin/reports/requests/export')->assertForbidden();
});

test('a request cell starting with a formula trigger gets a leading apostrophe', function () {
    RequestModel::factory()->create([
        'request_type_id' => RequestType::factory()->create(['name' => '@SUM(1+1)'])->id,
    ]);

    expect($this->csvRows($this->get('/api/admin/reports/requests/export'))[1][1])->toBe("'@SUM(1+1)");
});

test('the request export runs the same number of queries for 3 rows as for 60', function () {
    $department = Department::factory()->create();
    $createRequests = fn (int $count) => RequestModel::factory()->count($count)->create([
        'employee_id' => Employee::factory()->state(['department_id' => $department->id]),
        'current_step_id' => WorkflowStep::factory(),
    ]);
    $countStreamingQueries = function (): int {
        $response = $this->get('/api/admin/reports/requests/export');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response->streamedContent();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $createRequests(3);
    $queriesForThreeRows = $countStreamingQueries();

    $createRequests(57);
    $queriesForSixtyRows = $countStreamingQueries();

    expect($queriesForSixtyRows)->toBe($queriesForThreeRows)->toBeLessThanOrEqual(5);
    expect($this->csvRows($this->get('/api/admin/reports/requests/export')))->toHaveCount(61);
});
