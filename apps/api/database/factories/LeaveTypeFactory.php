<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)).' Leave',
            'code' => $this->faker->unique()->slug(2, false),
            'is_paid' => true,
            'is_balance_based' => true,
            'default_annual_entitlement' => 21.00,
            'allow_negative_balance' => false,
            'requires_attachment' => false,
            'max_consecutive_days' => null,
            'min_notice_days' => 0,
            'color' => '#2678C4',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function notBalanceBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_balance_based' => false,
            'default_annual_entitlement' => 0,
        ]);
    }

    public function requiringAttachment(): static
    {
        return $this->state(fn (array $attributes) => ['requires_attachment' => true]);
    }

    public function allowingNegativeBalance(): static
    {
        return $this->state(fn (array $attributes) => ['allow_negative_balance' => true]);
    }
}
