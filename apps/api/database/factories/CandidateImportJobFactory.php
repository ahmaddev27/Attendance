<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CandidateImportJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateImportJob>
 */
class CandidateImportJobFactory extends Factory
{
    protected $model = CandidateImportJob::class;

    /**
     * NOTE: `job_requirement_id` is intentionally omitted — JobRequirement
     * has no factory in the repo, so tests pass a real id via explicit
     * state. Mirrors the Phase 1 raw ::create pattern.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by_user_id' => User::factory(),
            'uploaded_filename' => 'candidates-'.$this->faker->uuid().'.csv',
            'storage_path' => 'recruitment/imports/'.$this->faker->uuid().'.csv',
            'total_rows' => 0,
            'created_candidates' => 0,
            'reused_candidates' => 0,
            'created_applications' => 0,
            'skipped_duplicates' => 0,
            'status' => 'pending',
            'errors' => null,
        ];
    }
}
