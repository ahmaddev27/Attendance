<?php

namespace Database\Factories;

use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone' => '+9627' . $this->faker->numerify('########'),
            'message' => $this->faker->sentence(),
            'status' => SmsStatus::Sent,
            'sent_at' => now(),
        ];
    }
}
