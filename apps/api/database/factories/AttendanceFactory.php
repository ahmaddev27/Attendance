<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Employee;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * Monotonic per-factory-instance counter so ->count(N)->create([...])
     * hands out N distinct dates. Faker's random dateTimeBetween('-30d')
     * has ~31 possible values, which collides against the
     * (employee_id, date) unique constraint the moment two rows share
     * an employee (e.g. `Attendance::factory()->count(2)->create(['employee_id' => $x])`).
     * Explicit sequence eliminates the flake without changing what
     * consumers see — dates still cluster in the last ~30 days.
     */
    private static int $dateOffset = 0;

    public function definition(): array
    {
        $offset = self::$dateOffset++ % 30;
        $date = Carbon::now()->subDays($offset)->startOfDay();
        $checkIn = $date->copy()->setTime(8, 0);
        $checkOut = $date->copy()->setTime(16, 0);

        return [
            'employee_id' => Employee::factory(),
            'date' => $date->toDateString(),
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'check_in_ip' => $this->faker->ipv4(),
            'check_out_ip' => $this->faker->ipv4(),
            'total_minutes' => 480,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => AttendanceStatus::Present,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_in_at' => null,
            'check_out_at' => null,
            'total_minutes' => null,
            'late_minutes' => null,
            'early_leave_minutes' => null,
            'overtime_minutes' => 0,
            'status' => AttendanceStatus::Absent,
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_in_at' => null,
            'check_out_at' => null,
            'total_minutes' => null,
            'late_minutes' => null,
            'early_leave_minutes' => null,
            'overtime_minutes' => 0,
            'status' => AttendanceStatus::OnLeave,
        ]);
    }
}
