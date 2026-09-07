<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\EmploymentType;
use App\Shared\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Produces a standalone, valid employee. Org-structure links
     * (department/team/position/manager) are left null by default —
     * attach them explicitly via ->for(...) or state overrides when a
     * test needs them, rather than paying for extra factory-created
     * departments/positions on every employee.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => fake()->unique()->numberBetween(1, 999999),
            'user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('07########'),
            'position_id' => null,
            'department_id' => null,
            'team_id' => null,
            'direct_manager_id' => null,
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'joining_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'birth_date' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'avatar_path' => null,
            'status' => EmployeeStatus::Active,
            'notes' => null,
            // Owned by the Attendance module (M3) — left null by default,
            // same rationale as department/team/position above.
            'work_schedule_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::Inactive,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::Terminated,
        ]);
    }
}
