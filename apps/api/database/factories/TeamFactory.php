<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => ucfirst(fake()->unique()->word()).' Team',
            'leader_id' => null,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
