<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('+1 day', '+30 days');

        return [
            'employee_id' => Employee::factory(),
            'start_date' => $date,
            'end_date' => $date,
            'note' => $this->faker->optional()->sentence(),
            'status' => LeaveStatus::Pending,
        ];
    }
}
