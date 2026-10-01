<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Modules\Attendance\Services\WorkingHoursCalculator;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\EmployeeStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Nightly working-hours recompute.
 *
 * Rolls over every attendance row for a target date (defaulting to
 * yesterday) and re-runs WorkingHoursCalculator against the employee's
 * CURRENT work schedule, so late_minutes / early_leave_minutes /
 * overtime_minutes / status reflect the current source of truth even
 * when the data that feeds them was changed after the fact:
 *
 *   - the admin edits the WorkSchedule's workdays or shift times,
 *   - a Holiday is backfilled for a day already scanned,
 *   - an admin corrects check_in_at / check_out_at later in the week,
 *   - an older row was never properly computed because the stamp
 *     handler had a bug (see migration 2026_09_20_100011).
 *
 * ## Idempotency
 *
 * The engine is a pure function of (check_in_at, check_out_at, schedule),
 * so re-running the same date against the same inputs yields the same
 * output — the "no drift" guarantee is intrinsic to the compute, not
 * layered on top of it. The command persists only when the computed
 * values differ from what's already stored, so a clean day is a no-op.
 *
 * ## Admin-correction policy
 *
 * The admin surface currently exposes no field for "manually set
 * late_minutes to 0 with a reason" — AttendanceController ships
 * `index` + `show` only, and there is no `late_reason` column on
 * attendance rows. The only authoritative inputs an admin can edit
 * today are `check_in_at` and `check_out_at`; both already drive the
 * compute. Picking a policy that respects a hypothetical
 * `late_reason` would silently diverge from how corrections actually
 * flow through the system now.
 *
 * The policy this command enforces: ALWAYS recompute from the stored
 * timestamps against the current schedule. The rows explicitly skipped
 * are the ones with no scan data to recompute from or no schedule to
 * recompute against:
 *
 *   - rows whose `check_in_at` is null (Absent, OnLeave, Holiday,
 *     Weekend, Remote, BusinessMission) — nothing to compute from and
 *     the stored status was set by a non-scan workflow,
 *   - rows whose employee has no `work_schedule_id` (or whose schedule
 *     has been deleted) — no reference frame for late / early / expected.
 *
 * When a future wave adds a `late_reason` / `override_locked` column,
 * extend the skip list — until then, overwriting is correct because
 * nothing else sets these values in the first place.
 */
class RecomputeAttendanceWorkingHours extends Command
{
    protected $signature = 'attendance:recompute-hours
                            {--date=yesterday : A date (Y-m-d), the literal word "yesterday", or "last-week" for the trailing 7 days}
                            {--employee-id= : Restrict the sweep to one employee (admin re-run / debugging)}
                            {--dry-run : Compute but do not persist; log the deltas that WOULD be written}';

    protected $description = 'Recompute late / early-leave / overtime minutes for attendance rows on a given date against the current schedule.';

    private const CHUNK_SIZE = 200;

    public function __construct(
        private readonly WorkingHoursCalculator $calculator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $dates = $this->resolveDates((string) $this->option('date'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        $employeeId = $this->option('employee-id') !== null
            ? (int) $this->option('employee-id')
            : null;
        $dryRun = (bool) $this->option('dry-run');

        $startedAt = Carbon::now();

        Log::info('attendance:recompute-hours starting', [
            'dates' => array_map(fn (Carbon $d) => $d->toDateString(), $dates),
            'employee_id' => $employeeId,
            'dry_run' => $dryRun,
        ]);

        $scanned = 0;
        $changed = 0;
        $unchanged = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($dates as $date) {
            $this->baseQuery($date, $employeeId)
                ->chunkById(self::CHUNK_SIZE, function ($rows) use (
                    &$scanned,
                    &$changed,
                    &$unchanged,
                    &$skipped,
                    &$errors,
                    $dryRun,
                ): void {
                    foreach ($rows as $attendance) {
                        $scanned++;

                        try {
                            $outcome = $this->recomputeOne($attendance, $dryRun);

                            match ($outcome) {
                                'changed' => $changed++,
                                'unchanged' => $unchanged++,
                                default => $skipped++,
                            };
                        } catch (\Throwable $e) {
                            // One bad row (orphaned employee reference,
                            // corrupt timestamp) must never halt the sweep
                            // — log for ops and keep going.
                            $errors++;
                            Log::warning('attendance:recompute-hours failed for attendance row', [
                                'attendance_id' => $attendance->id,
                                'employee_id' => $attendance->employee_id,
                                'date' => optional($attendance->date)->toDateString(),
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
        }

        $summary = sprintf(
            'attendance:recompute-hours done — scanned %d, changed %d, unchanged %d, skipped %d, errors %d (%s in %ds)',
            $scanned,
            $changed,
            $unchanged,
            $skipped,
            $errors,
            $dryRun ? 'dry-run' : 'persisted',
            (int) $startedAt->diffInSeconds(Carbon::now()),
        );

        Log::info($summary);
        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * @return list<Carbon>
     */
    private function resolveDates(string $raw): array
    {
        $tz = config('app.timezone');
        $raw = trim($raw);

        if ($raw === '' || strcasecmp($raw, 'yesterday') === 0) {
            return [Carbon::yesterday($tz)->startOfDay()];
        }

        if (strcasecmp($raw, 'last-week') === 0) {
            $end = Carbon::yesterday($tz)->startOfDay();

            // Trailing 7 calendar days ending YESTERDAY — today is
            // deliberately excluded so a mid-day run doesn't clobber
            // still-open sessions whose stamp handler will fire on
            // check-out later today.
            return array_map(
                fn (int $offset) => $end->copy()->subDays($offset),
                range(6, 0),
            );
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $raw, $tz);

        if ($parsed === false || $parsed->format('Y-m-d') !== $raw) {
            throw new \InvalidArgumentException(
                "Invalid --date value '{$raw}'. Use Y-m-d, 'yesterday', or 'last-week'."
            );
        }

        return [$parsed->startOfDay()];
    }

    /**
     * @return Builder<Attendance>
     */
    private function baseQuery(Carbon $date, ?int $employeeId): Builder
    {
        $dayStart = $date->toDateString();
        $dayEnd = $date->copy()->addDay()->toDateString();

        // Half-open range (>= today, < tomorrow) instead of = $dayStart so
        // the filter matches whether the DB driver stores the DATE column
        // as a pure 'Y-m-d' (MySQL / Postgres) or as a 'Y-m-d H:i:s' string
        // (SQLite in tests). A plain `where('date', $dayStart)` matches
        // only the pure-DATE form.
        $query = Attendance::query()
            ->with(['employee' => fn ($q) => $q->withTrashed(), 'employee.workSchedule'])
            ->whereNotNull('check_in_at')
            ->where('date', '>=', $dayStart)
            ->where('date', '<', $dayEnd);

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);

            return $query;
        }

        // Default sweep is scoped to ACTIVE staff — terminated / inactive
        // employees' historical rows don't change retroactively and
        // widening the pool past the first lazy load adds no value.
        $query->whereHas('employee', function (Builder $employeeQuery): void {
            $employeeQuery->where('status', EmployeeStatus::Active->value);
        });

        return $query;
    }

    /**
     * Recompute a single attendance row and persist the delta.
     *
     * @return 'changed'|'unchanged'|'skipped'
     */
    private function recomputeOne(Attendance $attendance, bool $dryRun): string
    {
        $employee = $attendance->employee;
        $schedule = $employee?->workSchedule;

        if (! $employee instanceof Employee || ! $schedule instanceof WorkSchedule) {
            return 'skipped';
        }

        $snapshot = [
            'total_minutes' => $attendance->total_minutes,
            'late_minutes' => $attendance->late_minutes,
            'early_leave_minutes' => $attendance->early_leave_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'status' => $attendance->status,
        ];

        // Working on a cloned row keeps the DB untouched on dry runs AND
        // isolates the diff comparison from the Eloquent dirty tracker.
        $candidate = $this->computeCandidate($attendance, $schedule);

        $diff = $this->diffAgainst($snapshot, $candidate);

        if ($diff === []) {
            return 'unchanged';
        }

        if ($dryRun) {
            Log::info('attendance:recompute-hours DRY would update', [
                'attendance_id' => $attendance->id,
                'employee_id' => $attendance->employee_id,
                'date' => optional($attendance->date)->toDateString(),
                'diff' => $diff,
            ]);

            $this->line(sprintf(
                'DRY attendance #%d (employee %d, %s): %s',
                $attendance->id,
                $attendance->employee_id,
                optional($attendance->date)->toDateString() ?? '?',
                $this->renderDiff($diff),
            ));

            return 'changed';
        }

        $attendance->forceFill($candidate)->save();

        return 'changed';
    }

    /**
     * @return array{total_minutes: ?int, late_minutes: int, early_leave_minutes: int, overtime_minutes: int, status: AttendanceStatus}
     */
    private function computeCandidate(Attendance $attendance, WorkSchedule $schedule): array
    {
        // Compute by replaying the same math WorkingHoursCalculator
        // runs on check-in / check-out, but WITHOUT persisting — the
        // caller handles writes so dry-run stays truly read-only.
        $checkIn = $attendance->check_in_at;
        $checkOut = $attendance->check_out_at;

        if ($checkIn === null) {
            // Guarded by the base query, but defensive — a row that lost
            // its check_in_at mid-flight should fall through as "no-op".
            return [
                'total_minutes' => $attendance->total_minutes,
                'late_minutes' => (int) ($attendance->late_minutes ?? 0),
                'early_leave_minutes' => (int) ($attendance->early_leave_minutes ?? 0),
                'overtime_minutes' => (int) ($attendance->overtime_minutes ?? 0),
                'status' => $attendance->status ?? AttendanceStatus::Present,
            ];
        }

        $lateMinutes = $this->lateMinutes($checkIn, $schedule);

        if ($checkOut === null) {
            // Open session: match the stampCheckInStatus contract — late
            // only, status Present / Late, no early/overtime stamping.
            return [
                'total_minutes' => $attendance->total_minutes,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => (int) ($attendance->early_leave_minutes ?? 0),
                'overtime_minutes' => (int) ($attendance->overtime_minutes ?? 0),
                'status' => $lateMinutes > 0 ? AttendanceStatus::Late : AttendanceStatus::Present,
            ];
        }

        $totalMinutes = (int) $checkIn->diffInMinutes($checkOut);
        $earlyLeaveMinutes = $this->earlyLeaveMinutes($checkOut, $schedule);
        $overtimeMinutes = max(0, $totalMinutes - $schedule->expectedMinutes());

        $status = match (true) {
            $lateMinutes > 0 => AttendanceStatus::Late,
            $earlyLeaveMinutes > 0 => AttendanceStatus::EarlyLeave,
            default => AttendanceStatus::Present,
        };

        return [
            'total_minutes' => $totalMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $status,
        ];
    }

    /**
     * Mirror of WorkingHoursCalculator::lateMinutes — kept here because
     * the shared helper is private on the service and we need the pure
     * number without the service's forceSave side effect.
     */
    private function lateMinutes(Carbon $checkInAt, WorkSchedule $schedule): int
    {
        if ($schedule->is_flexible || ! $schedule->check_in_time) {
            return 0;
        }

        $scheduledCheckIn = $checkInAt->copy()->setTimeFromTimeString(
            $schedule->check_in_time->format('H:i:s'),
        );

        if ($checkInAt->lessThanOrEqualTo($scheduledCheckIn)) {
            return 0;
        }

        return max(0, (int) $scheduledCheckIn->diffInMinutes($checkInAt) - $schedule->grace_late_minutes);
    }

    /**
     * Mirror of WorkingHoursCalculator::earlyLeaveMinutes — same reason
     * as lateMinutes above.
     */
    private function earlyLeaveMinutes(Carbon $checkOutAt, WorkSchedule $schedule): int
    {
        if ($schedule->is_flexible || ! $schedule->check_out_time) {
            return 0;
        }

        $scheduledCheckOut = $checkOutAt->copy()->setTimeFromTimeString(
            $schedule->check_out_time->format('H:i:s'),
        );

        if ($checkOutAt->greaterThanOrEqualTo($scheduledCheckOut)) {
            return 0;
        }

        return max(0, (int) $checkOutAt->diffInMinutes($scheduledCheckOut) - $schedule->grace_early_leave_minutes);
    }

    /**
     * @param  array{total_minutes: ?int, late_minutes: ?int, early_leave_minutes: ?int, overtime_minutes: ?int, status: ?AttendanceStatus}  $before
     * @param  array{total_minutes: ?int, late_minutes: int, early_leave_minutes: int, overtime_minutes: int, status: AttendanceStatus}  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffAgainst(array $before, array $after): array
    {
        $diff = [];

        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;

            if ($previous instanceof AttendanceStatus) {
                $previous = $previous->value;
            }

            $current = $value instanceof AttendanceStatus ? $value->value : $value;

            if ($previous === $current) {
                continue;
            }

            $diff[$key] = ['from' => $previous, 'to' => $current];
        }

        return $diff;
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $diff
     */
    private function renderDiff(array $diff): string
    {
        $parts = [];

        foreach ($diff as $key => $entry) {
            $from = $entry['from'] ?? 'null';
            $to = $entry['to'] ?? 'null';
            $parts[] = "{$key} {$from}→{$to}";
        }

        return implode(', ', $parts);
    }
}
