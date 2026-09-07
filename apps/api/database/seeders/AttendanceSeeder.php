<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Modules\Attendance\Services\WorkingHoursCalculator;
use App\Shared\Enums\AttendanceStatus;
use App\Shared\Enums\HolidayType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * NOTE FOR THE COORDINATOR: this class is intentionally not wired into
 * DatabaseSeeder::run() — per the M3 brief, that file is left for the
 * coordinator to edit. Add `AttendanceSeeder::class` to its $this->call([])
 * list, after DemoOrgSeeder, so newly seeded employees get the default
 * work schedule assigned automatically. Until then, run it explicitly:
 * `php artisan db:seed --class=AttendanceSeeder`.
 */
class AttendanceSeeder extends Seeder
{
    private const SAMPLE_ATTENDANCE_DAYS = 30;

    public function run(WorkingHoursCalculator $calculator): void
    {
        $schedule = $this->seedDefaultSchedule();
        $device = $this->seedDefaultDevice();
        $this->seedHolidays();

        if (! Schema::hasTable('employees')) {
            return;
        }

        $this->backfillEmployeeSchedules($schedule);
        $this->seedSampleAttendance($schedule, $device, $calculator);
    }

    private function seedDefaultSchedule(): WorkSchedule
    {
        return WorkSchedule::query()->updateOrCreate(
            ['name' => 'Standard'],
            [
                'timezone' => 'Asia/Amman',
                'check_in_time' => '08:00',
                'check_out_time' => '16:00',
                'min_hours_per_day' => 8.00,
                'grace_late_minutes' => 15,
                'grace_early_leave_minutes' => 15,
                'workdays' => [0, 1, 2, 3, 4], // Sunday-Thursday
                'is_flexible' => false,
                'is_active' => true,
            ],
        );
    }

    private function seedDefaultDevice(): AttendanceDevice
    {
        return AttendanceDevice::query()->updateOrCreate(
            ['name' => 'Main Office'],
            [
                'qr_token' => Str::random(64),
                'qr_rotates_every_seconds' => 300,
                'last_token_rotated_at' => now(),
                'is_active' => true,
            ],
        );
    }

    private function seedHolidays(): void
    {
        $currentYear = (int) now()->year;

        $holidays = [
            ['month' => 5, 'day' => 1, 'name' => 'Labor Day'],
            ['month' => 5, 'day' => 15, 'name' => 'Independence Day'],
        ];

        foreach ($holidays as $holiday) {
            // Matched by a Carbon instance rather than ->toDateString(): a
            // plain string bypasses Eloquent's date-cast formatting during
            // the underlying WHERE lookup, so a re-run would never find the
            // row it just inserted (mismatched 'Y-m-d' vs 'Y-m-d H:i:s') and
            // fail on the table's unique constraint instead of updating.
            Holiday::query()->updateOrCreate(
                [
                    'date' => Carbon::create($currentYear, $holiday['month'], $holiday['day']),
                    'name' => $holiday['name'],
                ],
                [
                    'type' => HolidayType::Official,
                    'is_recurring' => true,
                ],
            );
        }
    }

    /**
     * Any employee left without a work schedule (e.g. freshly created by
     * another module's seeder) is attached to the default one.
     */
    private function backfillEmployeeSchedules(WorkSchedule $schedule): void
    {
        Employee::query()
            ->whereNull('work_schedule_id')
            ->update(['work_schedule_id' => $schedule->id]);
    }

    /**
     * A rolling 30-workday attendance history for every seeded employee,
     * run through the real WorkingHoursCalculator so late/early/overtime
     * figures are internally consistent with the check-in/check-out times
     * generated here.
     */
    private function seedSampleAttendance(WorkSchedule $schedule, AttendanceDevice $device, WorkingHoursCalculator $calculator): void
    {
        $employees = Employee::query()->get();

        if ($employees->isEmpty()) {
            return;
        }

        foreach ($employees as $employee) {
            for ($daysAgo = self::SAMPLE_ATTENDANCE_DAYS; $daysAgo >= 1; $daysAgo--) {
                $date = Carbon::today()->subDays($daysAgo);

                if (! $schedule->isWorkday($date)) {
                    continue;
                }

                // Occasionally simulate a real absence so the sample data
                // isn't a wall-to-wall perfect attendance record.
                if (random_int(1, 20) === 1) {
                    Attendance::query()->updateOrCreate(
                        ['employee_id' => $employee->id, 'date' => $date],
                        ['status' => AttendanceStatus::Absent],
                    );

                    continue;
                }

                // Matched by the Carbon instance, not ->toDateString() — see
                // the comment on the holiday loop above for why a plain
                // string breaks re-running this seeder against existing data.
                $attendance = Attendance::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'date' => $date],
                    [
                        'check_in_at' => $date->copy()->setTime(8, random_int(0, 20)),
                        'check_out_at' => $date->copy()->setTime(16, random_int(0, 25)),
                        'check_in_device_id' => $device->id,
                        'check_out_device_id' => $device->id,
                        'status' => AttendanceStatus::Present,
                    ],
                );

                $calculator->computeForAttendance($attendance, $schedule);
            }
        }
    }
}
