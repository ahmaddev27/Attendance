<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Services;

use App\Models\Employee;
use App\Modules\Attendance\Repositories\HolidayRepository;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Counts the working days a leave request actually consumes: every
 * calendar day in [start, end] except the employee's non-workdays (per
 * their WorkSchedule::isWorkday()) and holidays.
 *
 * M4 only ever produces whole-day counts (no half-day support yet — see
 * LeaveEntitlementUnit's docblock for the placeholder that will carry
 * that later).
 */
class LeaveWorkingDaysCalculator
{
    public function __construct(
        private readonly HolidayRepository $holidays,
    ) {}

    public function compute(Employee $employee, Carbon $start, Carbon $end): float
    {
        $schedule = $employee->workSchedule;

        if ($schedule === null) {
            // Silently falling back to a guessed weekend pattern here would
            // risk quietly mis-billing an employee's balance. Force the
            // caller to fix the data (assign a schedule) instead.
            throw ValidationException::withMessages([
                'employee_id' => 'This employee has no work schedule assigned, so leave days cannot be computed.',
            ]);
        }

        // Fetch every holiday in the range in a single query and hydrate a
        // lookup set. Old code hit `Holiday::forDate($cursor)->exists()`
        // inside the day loop — a 30-day leave triggered 30 SELECT
        // EXISTS(...) queries on the holidays table (a documented N+1
        // caught in the DB audit).
        $holidayDates = $this->holidays->datesInRange($start, $end)->flip();

        $workingDays = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();
            if ($schedule->isWorkday($cursor) && ! $holidayDates->has($dateKey)) {
                $workingDays++;
            }

            $cursor->addDay();
        }

        return (float) $workingDays;
    }
}
