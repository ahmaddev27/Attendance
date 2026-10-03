<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateApplication>
 */
class CandidateApplicationFactory extends Factory
{
    protected $model = CandidateApplication::class;

    /**
     * NOTE: `job_requirement_id` and `current_stage_id` are intentionally
     * omitted from the defaults because JobRequirement and
     * RecruitmentPipelineStage have no Factory yet — callers pass real
     * ids via `->for($job)` / explicit state. Matches the raw ::create()
     * pattern Phase 1 Recruitment tests use.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        $year = (int) date('Y');

        return [
            'application_number' => sprintf('APP-%d-%05d', $year, $sequence),
            'candidate_id' => Candidate::factory(),
            'status' => 'applied',
            'source' => 'manual',
            'applied_at' => now(),
            'is_shortlisted' => false,
            'stage_entered_at' => now(),
        ];
    }

    public function shortlisted(): self
    {
        return $this->state(fn () => [
            'is_shortlisted' => true,
            'shortlisted_at' => now(),
            'status' => 'shortlisted',
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => 'Factory default rejection',
        ]);
    }
}
