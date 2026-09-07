<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SmsLog;
use App\Shared\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsLog>
 */
class SmsLogFactory extends Factory
{
    protected $model = SmsLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => $this->faker->numerify('07########'),
            'message' => $this->faker->sentence(),
            'status' => SmsStatus::Sent,
            'provider_response' => '0@OK',
            'error_code' => null,
            'sent_at' => now(),
            'notifiable_type' => null,
            'notifiable_id' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SmsStatus::Failed,
            'provider_response' => '10005@Low Balance',
            'error_code' => '10005',
        ]);
    }
}
