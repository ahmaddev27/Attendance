<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_number' => $this->faker->unique()->numberBetween(1001, 9999),
            'name' => $this->faker->name(),
            'phone' => '+9627' . $this->faker->unique()->numerify('########'),
            'email' => $this->faker->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}
