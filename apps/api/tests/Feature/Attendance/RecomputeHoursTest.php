<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Employee;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\EmployeeStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

afterEach(function () {
    Carbon::setTestNow();
    Attendance::flushEventListeners();
});

/**
 * Builds a late-arrival row backdated to the given attendance date, with
 * late_minutes intentionally stored as if the compute had never run (0),
 * so the recompute has a clear delta to find.
 */
function makeLateRow(Employee $employee, Carbon $date, string $checkIn = '08:30', string $checkOut = '16:00'): Attendance
{
    return Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $date->toDateString(),
        'check_in_at' => $date->copy()->setTimeFromTimeString($checkIn),
        'check_out_at' => $date->copy()->setTimeFromTimeString($checkOut),
        'total_minutes' => 450,
        'late_minutes' => 0,
        'early_leave_minutes' => 0,
        'overtime_minutes' => 0,
        'status' => AttendanceStatus::Present,
    ]);
}

test('dry-run computes but does NOT persist', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $row = makeLateRow($employee, $yesterday, '08:30', '16:00');

    $this->artisan('attendance:recompute-hours', ['--dry-run' => true])
        ->assertExitCode(0);

    $row->refresh();

    expect($row->late_minutes)->toBe(0)
        ->and($row->status)->toBe(AttendanceStatus::Present);
});

test('recomputes a row after the schedule was changed retroactively', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 12));

    // Originally the shift started at 09:00 so an 08:30 arrival was
    // on time. The admin changes the schedule to 08:00 after the fact
    // — the nightly sweep must pick up that 08:30 is now 30m late
    // (minus the 0 grace minutes used here for a clean assertion).
    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
        'grace_early_leave_minutes' => 0,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $row = makeLateRow($employee, $yesterday, '08:30', '16:00');

    $this->artisan('attendance:recompute-hours')
        ->assertExitCode(0);

    $row->refresh();

    expect($row->late_minutes)->toBe(30)
        ->and($row->total_minutes)->toBe(450)
        ->and($row->status)->toBe(AttendanceStatus::Late);
});

test('skips rows that have no check-in to recompute from (OnLeave, Absent, etc.)', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $leaveRow = Attendance::factory()->onLeave()->create([
        'employee_id' => $employee->id,
        'date' => $yesterday->toDateString(),
    ]);

    $this->artisan('attendance:recompute-hours')
        ->assertExitCode(0);

    $leaveRow->refresh();

    expect($leaveRow->status)->toBe(AttendanceStatus::OnLeave)
        ->and($leaveRow->check_in_at)->toBeNull()
        ->and($leaveRow->late_minutes)->toBeNull();
});

test('is idempotent — running twice produces the same values the second time', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $row = makeLateRow($employee, $yesterday, '08:45', '16:00');

    $this->artisan('attendance:recompute-hours')->assertExitCode(0);
    $row->refresh();
    $afterFirst = [
        'late' => $row->late_minutes,
        'early' => $row->early_leave_minutes,
        'ot' => $row->overtime_minutes,
        'status' => $row->status,
    ];

    $this->artisan('attendance:recompute-hours')->assertExitCode(0);
    $row->refresh();
    $afterSecond = [
        'late' => $row->late_minutes,
        'early' => $row->early_leave_minutes,
        'ot' => $row->overtime_minutes,
        'status' => $row->status,
    ];

    expect($afterSecond)->toBe($afterFirst)
        ->and($afterFirst['late'])->toBe(45);
});

test('--employee-id scopes the sweep to a single employee', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
    ]);
    $target = makeEmployeeWithSchedule($schedule);
    $bystander = makeEmployeeWithSchedule($schedule);

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $targetRow = makeLateRow($target, $yesterday);
    $bystanderRow = makeLateRow($bystander, $yesterday);

    $this->artisan('attendance:recompute-hours', ['--employee-id' => $target->id])
        ->assertExitCode(0);

    $targetRow->refresh();
    $bystanderRow->refresh();

    expect($targetRow->late_minutes)->toBe(30)
        ->and($targetRow->status)->toBe(AttendanceStatus::Late)
        ->and($bystanderRow->late_minutes)->toBe(0)
        ->and($bystanderRow->status)->toBe(AttendanceStatus::Present);
});

test('--date=YYYY-MM-DD scopes the sweep to that specific date', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $targetDate = Carbon::create(2026, 10, 10);
    $otherDate = Carbon::create(2026, 10, 11);

    $targetRow = makeLateRow($employee, $targetDate);
    $otherRow = makeLateRow($employee, $otherDate);

    $this->artisan('attendance:recompute-hours', ['--date' => $targetDate->toDateString()])
        ->assertExitCode(0);

    $targetRow->refresh();
    $otherRow->refresh();

    expect($targetRow->late_minutes)->toBe(30)
        ->and($otherRow->late_minutes)->toBe(0);
});

test('--date=last-week covers the trailing 7 days ending yesterday', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $sixDaysAgo = $yesterday->copy()->subDays(6);
    $eightDaysAgo = $yesterday->copy()->subDays(8);

    $insideStart = makeLateRow($employee, $sixDaysAgo);
    $insideEnd = makeLateRow($employee, $yesterday);
    $outsideWindow = makeLateRow($employee, $eightDaysAgo);

    $this->artisan('attendance:recompute-hours', ['--date' => 'last-week'])
        ->assertExitCode(0);

    $insideStart->refresh();
    $insideEnd->refresh();
    $outsideWindow->refresh();

    expect($insideStart->late_minutes)->toBe(30)
        ->and($insideEnd->late_minutes)->toBe(30)
        ->and($outsideWindow->late_minutes)->toBe(0);
});

test('rejects an invalid --date value', function () {
    $this->artisan('attendance:recompute-hours', ['--date' => '2026/10/01'])
        ->assertExitCode(2); // Command::INVALID
});

test('a per-row failure is logged and the sweep continues to the next row', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
    ]);
    $victimEmp = makeEmployeeWithSchedule($schedule);
    $survivorEmp = makeEmployeeWithSchedule($schedule);

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $victimRow = makeLateRow($victimEmp, $yesterday);
    $survivorRow = makeLateRow($survivorEmp, $yesterday);

    // The 'saving' hook fires inside the forceFill()->save() the
    // command issues for a changed row — throwing from it is the
    // simplest way to prove the sweep does not halt on a bad save.
    Attendance::saving(function (Attendance $model) use ($victimRow): bool {
        if ((int) $model->id === (int) $victimRow->id) {
            throw new \RuntimeException('simulated save failure');
        }

        return true;
    });

    Log::spy();

    $this->artisan('attendance:recompute-hours')->assertExitCode(0);

    $survivorRow->refresh();
    $victimRow->refresh();

    expect($survivorRow->late_minutes)->toBe(30)
        ->and($victimRow->late_minutes)->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => $message === 'attendance:recompute-hours failed for attendance row')
        ->atLeast()
        ->once();
});

test('default sweep skips inactive employees', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
    ]);

    $active = makeEmployeeWithSchedule($schedule);
    $terminated = makeEmployeeWithSchedule($schedule);
    $terminated->forceFill(['status' => EmployeeStatus::Terminated])->save();

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $activeRow = makeLateRow($active, $yesterday);
    $terminatedRow = makeLateRow($terminated, $yesterday);

    $this->artisan('attendance:recompute-hours')->assertExitCode(0);

    $activeRow->refresh();
    $terminatedRow->refresh();

    expect($activeRow->late_minutes)->toBe(30)
        ->and($terminatedRow->late_minutes)->toBe(0);
});

test('--employee-id overrides the active-employee filter for admin re-runs', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 12));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 0,
    ]);

    $terminated = makeEmployeeWithSchedule($schedule);
    $terminated->forceFill(['status' => EmployeeStatus::Terminated])->save();

    $yesterday = Carbon::yesterday(config('app.timezone'));
    $row = makeLateRow($terminated, $yesterday);

    $this->artisan('attendance:recompute-hours', ['--employee-id' => $terminated->id])
        ->assertExitCode(0);

    $row->refresh();

    expect($row->late_minutes)->toBe(30);
});
