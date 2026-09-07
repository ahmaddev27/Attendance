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
            'code' => $this->faker->unique()->slug(2, false),
            'color' => $this->faker->hexColor(),
            'sort_order' => 0,
        ];
    }
}
