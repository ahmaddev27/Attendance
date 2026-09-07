<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaskTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTag>
 */
class TaskTagFactory extends Factory
{
    protected $model = TaskTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
            'color' => $this->faker->hexColor(),
        ];
    }
}
