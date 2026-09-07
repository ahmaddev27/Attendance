<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    protected $model = WorkSchedule::class;

    public function definition(): array
    {
        return [
            'name' => 'Standard',
            'timezone' => 'Asia/Amman',
            'check_in_time' => '08:00',
            'check_out_time' => '16:00',
            'min_hours_per_day' => 8.00,
            'grace_late_minutes' => 15,
            'grace_early_leave_minutes' => 15,
            'workdays' => [0, 1, 2, 3, 4], // Sun-Thu
            'is_flexible' => false,
            'is_active' => true,
        ];
    }

    /**
     * A flexible schedule: no fixed check-in/check-out time, only a
     * minimum-hours requirement.
     */
    public function flexible(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_in_time' => null,
            'check_out_time' => null,
            'is_flexible' => true,
        ]);
    }
}
