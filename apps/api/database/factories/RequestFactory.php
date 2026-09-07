<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\RequestType;
use App\Shared\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestModel>
 */
class RequestFactory extends Factory
{
    protected $model = RequestModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_number' => 'REQ-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 4, '0', STR_PAD_LEFT),
            'employee_id' => Employee::factory(),
            'request_type_id' => RequestType::factory(),
            'form_data' => [],
            'status' => RequestStatus::Pending,
            'current_step_id' => null,
            'submitted_at' => now(),
            'completed_at' => null,
        ];
    }
}
