<?php

use App\Models\Employee;
use App\Models\Holiday;
use App\Modules\Leaves\Services\LeaveWorkingDaysCalculator;
use App\Shared\Enums\HolidayType;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

test('a full work week span excludes the two weekend days', function () {
    $schedule = makeWorkSchedule(['workdays' => [1, 2, 3, 4, 5]]); // Mon-Fri
    $employee = makeEmployeeWithSchedule($schedule);

    // A Monday through the following Sunday: 5 workdays + 2 weekend days.
    $start = Carbon::parse('next monday');
    $end = $start->copy()->addDays(6);

    $days = app(LeaveWorkingDaysCalculator::class)->compute($employee, $start, $end);

    expect($days)->toBe(5.0);
});

test('a holiday inside the range is excluded even though it falls on a workday', function () {
    $schedule = makeWorkSchedule(['workdays' => [1, 2, 3, 4, 5]]); // Mon-Fri
    $employee = makeEmployeeWithSchedule($schedule);

    $start = Carbon::parse('next monday');
    $end = $start->copy()->addDays(4); // Mon-Fri, 5 workdays before the holiday is applied

    Holiday::factory()->create([
        'date' => $start->copy()->addDays(2)->toDateString(), // the Wednesday in range
        'type' => HolidayType::Official,
        'is_recurring' => false,
    ]);

    $days = app(LeaveWorkingDaysCalculator::class)->compute($employee, $start, $end);

    expect($days)->toBe(4.0);
});

test('a range that falls entirely on the weekend counts zero days', function () {
    $schedule = makeWorkSchedule(['workdays' => [1, 2, 3, 4, 5]]); // Mon-Fri
    $employee = makeEmployeeWithSchedule($schedule);

    $start = Carbon::parse('next saturday');
    $end = $start->copy()->addDay(); // Saturday + Sunday

    $days = app(LeaveWorkingDaysCalculator::class)->compute($employee, $start, $end);

    expect($days)->toBe(0.0);
});

test('a single-day range on a workday counts as one day', function () {
    $schedule = makeWorkSchedule(['workdays' => [1, 2, 3, 4, 5]]);
    $employee = makeEmployeeWithSchedule($schedule);

    $monday = Carbon::parse('next monday');

    $days = app(LeaveWorkingDaysCalculator::class)->compute($employee, $monday, $monday->copy());

    expect($days)->toBe(1.0);
});

test('an employee with no work schedule cannot have their leave days computed', function () {
    $employee = Employee::factory()->create(['work_schedule_id' => null]);

    $start = Carbon::parse('next monday');

    expect(fn () => app(LeaveWorkingDaysCalculator::class)->compute($employee, $start, $start->copy()->addDay()))
        ->toThrow(ValidationException::class);
});
