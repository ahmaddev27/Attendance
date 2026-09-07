<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\User;
use App\Shared\Enums\TaskAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskHistory>
 */
class TaskHistoryFactory extends Factory
{
    protected $model = TaskHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'action' => TaskAction::Created,
            'old_value' => null,
            'new_value' => null,
            'created_at' => now(),
        ];
    }
}
