<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->unique()->word()).' Department',
            'code' => strtoupper(fake()->unique()->lexify('DEPT-????')),
            'parent_id' => null,
            'manager_id' => null,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
