<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        // Tests that care about dedup set email/phone explicitly; the
        // default here is a unique email per invocation so the UNIQUE
        // constraint-free factory never accidentally dedups two rows.
        $year = (int) date('Y');

        return [
            'candidate_number' => sprintf('CAN-%d-%04d', $year, $sequence),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '+9705'.$this->faker->numerify('########'),
            'country' => 'Palestine',
            'city' => 'Gaza',
            'headline' => $this->faker->jobTitle(),
            'years_of_experience' => $this->faker->numberBetween(0, 15),
            'status' => 'active',
            'source' => 'manual',
            'salary_currency' => 'USD',
            'created_by_user_id' => User::factory(),
        ];
    }

    public function blacklisted(): self
    {
        return $this->state(fn () => ['status' => 'blacklisted']);
    }
}
