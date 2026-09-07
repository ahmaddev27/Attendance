<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaskPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskPriority>
 */
class TaskPriorityFactory extends Factory
{
    protected $model = TaskPriority::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
            // slug(2) can exceed 20 chars (schema limit) with long dictionary
            // words. lexify gives a fixed 8-char code that always fits.
            'code' => strtoupper($this->faker->unique()->lexify('PRI-????')),
            'color' => $this->faker->hexColor(),
            'sort_order' => 0,
        ];
    }
}
