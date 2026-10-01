<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Employee;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\EmployeeStatus;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

beforeEach(function (): void {
    $this->actingAsSuperAdmin();
    // EmployeeService::create() seeds a login user with the 'employee' role.
    Role::findOrCreate('employee', 'web');

    // The dashboard caches KPIs for 15s under `admin.kpis` /
    // `admin.kpis.company.{id}` — flush so each test starts cold.
    Cache::flush();
});

/**
 * Dashboard KPIs — ensures the `?company_id` filter added in c856558 is
 * actually reachable from the frontend wiring that this follow-up ships.
 *
 * The KPIs collapse four independent counts (employees, today attendance,
 * pending approvals, open tasks) through
 * AdminDashboardService::kpis($today, $companyId). The service was already
 * covered at the unit level (see AdminDashboardTest) — these tests verify
 * end-to-end that the HTTP boundary honors the query param, 422s on an
 * unknown id, and keeps the global behavior intact when the param is
 * absent.
 */
test('dashboard kpis filter employee counts by company_id', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    Employee::factory()->count(2)->create([
        'company_id' => $orgA['company']->id,
        'status' => EmployeeStatus::Active,
    ]);
    Employee::factory()->count(5)->create([
        'company_id' => $orgB['company']->id,
        'status' => EmployeeStatus::Active,
    ]);

    $this->getJson("/api/admin/dashboard/kpis?company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonPath('data.employees.total', 2)
        ->assertJsonPath('data.employees.active', 2);
});

test('dashboard kpis without company_id returns global counts', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    Employee::factory()->count(2)->create([
        'company_id' => $orgA['company']->id,
        'status' => EmployeeStatus::Active,
    ]);
    Employee::factory()->count(3)->create([
        'company_id' => $orgB['company']->id,
        'status' => EmployeeStatus::Active,
    ]);

    $this->getJson('/api/admin/dashboard/kpis')
        ->assertOk()
        ->assertJsonPath('data.employees.total', 5);
});

test('dashboard kpis filter today attendance by company_id', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    $empA = Employee::factory()->create(['company_id' => $orgA['company']->id]);
    $empB = Employee::factory()->create(['company_id' => $orgB['company']->id]);

    // Insert attendance rows via DB::table to sidestep the Attendance
    // model's `date` Eloquent cast — on SQLite the cast widens the value
    // to `Y-m-d 00:00:00`, and the service's
    // `->where('date', $todayDate)` then misses it. See the dev-workflow
    // memory for the pre-existing AdminDashboardTest flake with the same
    // root cause (green on MySQL CI, local SQLite only).
    $today = now()->toDateString();
    \Illuminate\Support\Facades\DB::table('attendances')->insert([
        [
            'employee_id' => $empA->id,
            'date' => $today,
            'status' => AttendanceStatus::Present->value,
            'check_in_at' => now(),
            'check_out_at' => now()->addHours(8),
            'total_minutes' => 480,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'employee_id' => $empB->id,
            'date' => $today,
            'status' => AttendanceStatus::Present->value,
            'check_in_at' => now(),
            'check_out_at' => now()->addHours(8),
            'total_minutes' => 480,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->getJson("/api/admin/dashboard/kpis?company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonPath('data.today.present', 1);
});

test('dashboard kpis rejects unknown company_id with 422', function (): void {
    $this->getJson('/api/admin/dashboard/kpis?company_id=99999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_id');
});

test('dashboard kpis cache is keyed per company so switching does not leak', function (): void {
    // Verify both cache keys exist independently — the real-world bug would
    // be a scoped admin seeing another company's cached counts because the
    // single `admin.kpis` key was reused for every scope.
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    Employee::factory()->count(4)->create([
        'company_id' => $orgA['company']->id,
        'status' => EmployeeStatus::Active,
    ]);
    Employee::factory()->count(1)->create([
        'company_id' => $orgB['company']->id,
        'status' => EmployeeStatus::Active,
    ]);

    // Prime A, then B — if the cache key wasn't per-company, B's response
    // would reuse A's data.
    $responseA = $this->getJson("/api/admin/dashboard/kpis?company_id={$orgA['company']->id}");
    $responseA->assertOk()->assertJsonPath('data.employees.total', 4);

    $responseB = $this->getJson("/api/admin/dashboard/kpis?company_id={$orgB['company']->id}");
    $responseB->assertOk()->assertJsonPath('data.employees.total', 1);
});
