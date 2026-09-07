<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'title' => fake()->unique()->jobTitle(),
            'code' => strtoupper(fake()->unique()->lexify('POS-????')),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
