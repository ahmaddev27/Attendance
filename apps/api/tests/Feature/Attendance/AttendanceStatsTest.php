<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Company;
use App\Models\Employee;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Support\Carbon;

test('stats endpoint requires authentication', function () {
    $this->getJson('/api/admin/attendance/stats')->assertUnauthorized();
});

test('stats endpoint returns the full tile shape', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();
    Attendance::factory()->count(3)->create(['employee_id' => $employee->id]);

    $this->getJson('/api/admin/attendance/stats')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'total_rows', 'unique_employees',
            'present_count', 'absent_count', 'late_count', 'on_leave_count',
            'holiday_count', 'weekend_count',
            'total_hours', 'total_overtime_hours', 'avg_hours_per_day',
            'onsite_count', 'remote_count', 'unknown_origin_count',
        ]]);
});

test('stats totals aggregate across statuses', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();

    // 2 present (480m each), 1 absent, 1 on-leave on distinct dates so the
    // UNIQUE(employee_id, date) constraint holds.
    $base = Carbon::create(2026, 10, 1);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $base->toDateString(),
        'status' => AttendanceStatus::Present,
        'total_minutes' => 480,
        'overtime_minutes' => 30,
    ]);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $base->copy()->addDay()->toDateString(),
        'status' => AttendanceStatus::Present,
        'total_minutes' => 480,
        'overtime_minutes' => 0,
    ]);
    Attendance::factory()->absent()->create([
        'employee_id' => $employee->id,
        'date' => $base->copy()->addDays(2)->toDateString(),
    ]);
    Attendance::factory()->onLeave()->create([
        'employee_id' => $employee->id,
        'date' => $base->copy()->addDays(3)->toDateString(),
    ]);

    $this->getJson('/api/admin/attendance/stats')
        ->assertOk()
        ->assertJsonPath('data.total_rows', 4)
        ->assertJsonPath('data.unique_employees', 1)
        ->assertJsonPath('data.present_count', 2)
        ->assertJsonPath('data.absent_count', 1)
        ->assertJsonPath('data.on_leave_count', 1)
        ->assertJsonPath('data.total_hours', 16)
        ->assertJsonPath('data.total_overtime_hours', 0.5);
});

test('stats origin counts tally onsite, remote, and unknown rows', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();

    $onsiteDevice = AttendanceDevice::factory()->create(['ip_whitelist' => ['10.0.0.0/24']]);
    $emptyDevice = AttendanceDevice::factory()->create(['ip_whitelist' => []]);

    $base = Carbon::create(2026, 10, 1);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $base->toDateString(),
        'check_in_ip' => '10.0.0.9',
        'check_in_device_id' => $onsiteDevice->id,
    ]);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $base->copy()->addDay()->toDateString(),
        'check_in_ip' => '203.0.113.7',
        'check_in_device_id' => $onsiteDevice->id,
    ]);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $base->copy()->addDays(2)->toDateString(),
        'check_in_ip' => '198.51.100.1',
        'check_in_device_id' => $emptyDevice->id,
    ]);

    $this->getJson('/api/admin/attendance/stats')
        ->assertOk()
        ->assertJsonPath('data.onsite_count', 1)
        ->assertJsonPath('data.remote_count', 1)
        ->assertJsonPath('data.unknown_origin_count', 1);
});

test('stats honour the company_id filter', function () {
    actingAsAdmin();
    $acme = Company::factory()->create(['name' => 'ACME']);
    $other = Company::factory()->create(['name' => 'Other']);

    $acmeEmployee = Employee::factory()->create(['company_id' => $acme->id, 'work_schedule_id' => makeWorkSchedule()->id]);
    $otherEmployee = Employee::factory()->create(['company_id' => $other->id, 'work_schedule_id' => makeWorkSchedule()->id]);

    Attendance::factory()->count(2)->create(['employee_id' => $acmeEmployee->id]);
    Attendance::factory()->count(3)->create(['employee_id' => $otherEmployee->id]);

    $this->getJson("/api/admin/attendance/stats?company_id={$acme->id}")
        ->assertOk()
        ->assertJsonPath('data.total_rows', 2)
        ->assertJsonPath('data.unique_employees', 1);
});

test('stats honour the date range filter', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-09-10',
    ]);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-10-05',
    ]);
    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-10-15',
    ]);

    $this->getJson('/api/admin/attendance/stats?date_from=2026-10-01&date_to=2026-10-31')
        ->assertOk()
        ->assertJsonPath('data.total_rows', 2);
});
