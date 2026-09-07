<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Services;

use App\Models\Employee;
use App\Models\Holiday;
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

        $workingDays = 0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if ($schedule->isWorkday($cursor) && ! Holiday::forDate($cursor)->exists()) {
                $workingDays++;
            }

            $cursor->addDay();
        }

        return (float) $workingDays;
    }
}
