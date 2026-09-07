<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStatus>
 */
class TaskStatusFactory extends Factory
{
    protected $model = TaskStatus::class;

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
            'is_done_state' => false,
            'is_cancelled_state' => false,
        ];
    }

    public function doneState(): static
    {
        return $this->state(fn (array $attributes) => ['is_done_state' => true]);
    }

    public function cancelledState(): static
    {
        return $this->state(fn (array $attributes) => ['is_cancelled_state' => true]);
    }
}
