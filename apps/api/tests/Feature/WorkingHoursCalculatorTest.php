<?php

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Modules\Attendance\Services\WorkingHoursCalculator;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\HolidayType;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('monthly summary counts present, absent, leave, holiday and weekend days correctly', function () {
    // "Today" is well after the test month, so no day in it is treated as
    // "hasn't happened yet".
    Carbon::setTestNow(Carbon::create(2026, 2, 28, 12));

    $schedule = makeWorkSchedule([
        'workdays' => [1, 2, 3, 4, 5], // Mon-Fri
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $year = 2026;
    $month = 1; // January 2026 — fully in the past relative to the faked "today".

    $holidayDate = Carbon::create($year, $month, 1);
    Holiday::factory()->create([
        'date' => $holidayDate->toDateString(),
        'type' => HolidayType::Official,
        'is_recurring' => false,
    ]);

    $expectedHolidayDays = 0;
    $expectedWeekendDays = 0;
    $expectedPresentDates = [];
    $leaveAssigned = false;
    $absentAssigned = false;

    $cursor = Carbon::create($year, $month, 1);
    $end = $cursor->copy()->endOfMonth();

    while ($cursor->lte($end)) {
        if ($cursor->isSameDay($holidayDate)) {
            $expectedHolidayDays++;
        } elseif (! in_array($cursor->dayOfWeek, [1, 2, 3, 4, 5], true)) {
            $expectedWeekendDays++;
        } elseif (! $leaveAssigned) {
            Attendance::factory()->onLeave()->create([
                'employee_id' => $employee->id,
                'date' => $cursor->toDateString(),
            ]);
            $leaveAssigned = true;
        } elseif (! $absentAssigned) {
            // Deliberately no attendance row for this workday — it must
            // resolve to "absent" since it is in the past.
            $absentAssigned = true;
        } else {
            $expectedPresentDates[] = $cursor->toDateString();
            Attendance::factory()->create([
                'employee_id' => $employee->id,
                'date' => $cursor->toDateString(),
                'check_in_at' => $cursor->copy()->setTime(8, 0),
                'check_out_at' => $cursor->copy()->setTime(16, 0),
                'total_minutes' => 480,
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => AttendanceStatus::Present,
            ]);
        }

        $cursor->addDay();
    }

    $summary = app(WorkingHoursCalculator::class)->monthlySummary($employee, $year, $month);

    expect($summary->holidayDays)->toBe($expectedHolidayDays)
        ->and($summary->weekendDays)->toBe($expectedWeekendDays)
        ->and($summary->leaveDays)->toBe(1)
        ->and($summary->absentDays)->toBe(1)
        ->and($summary->presentDays)->toBe(count($expectedPresentDates))
        ->and($summary->totalWorkingDays)->toBe($summary->presentDays + $summary->absentDays + $summary->leaveDays)
        ->and($summary->totalMinutes)->toBe(count($expectedPresentDates) * 480)
        ->and($summary->expectedMinutes)->toBe($summary->totalWorkingDays * 480)
        ->and($summary->attendancePercentage)->toBe(round($summary->presentDays / $summary->totalWorkingDays * 100, 2));
});

test('a flexible schedule never produces late or early-leave minutes', function () {
    $schedule = WorkSchedule::factory()->flexible()->create();
    $employee = makeEmployeeWithSchedule($schedule);

    $today = Carbon::today();

    // These times would be "late" (10:15 check-in) and "early" (14:00
    // check-out) under a fixed schedule — but a flexible one has no
    // check_in_time/check_out_time to compare against.
    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $today->toDateString(),
        'check_in_at' => $today->copy()->setTime(10, 15),
        'check_out_at' => $today->copy()->setTime(14, 0),
    ]);

    app(WorkingHoursCalculator::class)->computeForAttendance($attendance, $schedule);

    expect($attendance->late_minutes)->toBe(0)
        ->and($attendance->early_leave_minutes)->toBe(0)
        ->and($attendance->status)->toBe(AttendanceStatus::Present);
});

test('late minutes report the raw delta — grace governs the status, not the number', function () {
    // Owner reported 2026-10-05: 9:00 schedule, scan-in 9:55 was showing
    // 40 late minutes (= 55 - 15 grace). Grace should only hide the
    // "متأخر" label when the lateness is inside it, not shrink the
    // reported minutes.
    $schedule = makeWorkSchedule([
        'check_in_time' => '09:00',
        'check_out_time' => '17:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 15,
        'grace_early_leave_minutes' => 15,
        'timezone' => 'Asia/Gaza',
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $today = Carbon::today();

    $late = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $today->toDateString(),
        'check_in_at' => Carbon::parse($today->toDateString().' 09:55:00', 'Asia/Gaza'),
        'check_out_at' => Carbon::parse($today->toDateString().' 17:00:00', 'Asia/Gaza'),
    ]);

    app(WorkingHoursCalculator::class)->computeForAttendance($late, $schedule);

    expect($late->late_minutes)->toBe(55)
        ->and($late->status)->toBe(AttendanceStatus::Late);

    // Inside the grace window: 7 minutes late is NOT flagged "متأخر"
    // (status = Present) but the raw 7 minutes are still reported.
    $within = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $today->copy()->subDay()->toDateString(),
        'check_in_at' => Carbon::parse($today->copy()->subDay()->toDateString().' 09:07:00', 'Asia/Gaza'),
        'check_out_at' => Carbon::parse($today->copy()->subDay()->toDateString().' 17:00:00', 'Asia/Gaza'),
    ]);

    app(WorkingHoursCalculator::class)->computeForAttendance($within, $schedule);

    expect($within->late_minutes)->toBe(7)
        ->and($within->status)->toBe(AttendanceStatus::Present);
});

test('overtime is computed from raw total minutes and is not reduced by grace periods', function () {
    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'min_hours_per_day' => 8,
        'grace_late_minutes' => 15,
        'grace_early_leave_minutes' => 15,
    ]);
    $employee = makeEmployeeWithSchedule($schedule);

    $today = Carbon::today();

    // On time in, 20 minutes late out (i.e. 20 minutes of overtime) — well
    // inside neither grace window, so grace must not shrink the overtime.
    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $today->toDateString(),
        'check_in_at' => $today->copy()->setTime(8, 0),
        'check_out_at' => $today->copy()->setTime(16, 20),
    ]);

    app(WorkingHoursCalculator::class)->computeForAttendance($attendance, $schedule);

    expect($attendance->overtime_minutes)->toBe(20)
        ->and($attendance->late_minutes)->toBe(0)
        ->and($attendance->early_leave_minutes)->toBe(0)
        ->and($attendance->status)->toBe(AttendanceStatus::Present);
});
