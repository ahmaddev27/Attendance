<?php

use App\Models\Attendance;
use App\Modules\Attendance\Services\WorkingHoursCalculator;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Database\Migrations\Migration;

/**
 * One-shot backfill for rows created before AttendanceService::checkIn
 * started stamping late_minutes + status on the check-in itself.
 *
 * Symptom: an employee scans in at 3:40 PM against an 8 AM shift, the
 * admin table shows "present, 0 late" until they eventually scan out
 * (which triggers the full compute). This migration re-runs the
 * late-only compute on every open row so today's data agrees with
 * what the new code produces going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        /** @var WorkingHoursCalculator $calculator */
        $calculator = app(WorkingHoursCalculator::class);

        Attendance::query()
            ->with('employee.workSchedule')
            ->whereNotNull('check_in_at')
            // Filter strictly to OPEN sessions — the migration's whole
            // point per its filename. Without this, closed rows whose
            // late_minutes were legitimately corrected to 0 by an admin
            // (or by the full check-out compute) get re-stamped here,
            // undoing that correction on every re-run.
            ->whereNull('check_out_at')
            ->where(function ($q) {
                $q->whereNull('late_minutes')->orWhere('late_minutes', 0);
            })
            ->where('status', AttendanceStatus::Present->value)
            ->chunkById(200, function ($rows) use ($calculator): void {
                foreach ($rows as $attendance) {
                    $schedule = $attendance->employee?->workSchedule;
                    if (! $schedule) {
                        continue;
                    }
                    $calculator->stampCheckInStatus($attendance, $schedule);
                }
            });
    }

    public function down(): void
    {
        // Intentionally empty — recomputing "back to zero" would just
        // resurrect the bug this migration is here to fix.
    }
};
