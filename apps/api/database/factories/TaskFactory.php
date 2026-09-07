<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_task_id' => null,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status_id' => TaskStatus::factory(),
            'priority_id' => TaskPriority::factory(),
            'created_by' => Employee::factory(),
            'assigned_to' => null,
            'estimated_hours' => null,
            'actual_hours' => null,
            'progress_percent' => 0,
            'start_date' => null,
            'due_date' => null,
            'completed_at' => null,
        ];
    }

    public function assignedTo(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => ['assigned_to' => $employee->id]);
    }

    public function subtaskOf(Task $parent): static
    {
        return $this->state(fn (array $attributes) => ['parent_task_id' => $parent->id]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
    }
}
