<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Holiday;
use App\Shared\Enums\HolidayType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->dateTimeBetween('-6 months', '+6 months')->format('Y-m-d'),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'type' => HolidayType::Official,
            'is_recurring' => false,
            'description' => null,
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => ['is_recurring' => true]);
    }
}
