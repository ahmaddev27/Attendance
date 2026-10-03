<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment Phase 2 — one Screening row per CandidateApplication.
 * A re-screen overwrites the same row (Phase 2 does not keep screening
 * version history, see Decision D5 in the plan). The schema that the
 * `scorecard` JSON validates against lives on
 * `recruitment_pipeline_stages.screening_schema` (D3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_screenings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')
                ->unique() // 1:1 with Application — enforce it at the DB.
                ->constrained('candidate_applications')
                ->cascadeOnDelete();
            $table->foreignId('scored_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Answers keyed by the schema's field keys. Validation lives
            // in CandidateScreeningService (per-field type checks).
            $table->json('scorecard');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->boolean('passed')->default(false);
            $table->string('recommendation', 30)->nullable();
            // 'advance'|'reject'|'hold'
            $table->text('notes')->nullable();
            $table->timestamp('scored_at');

            $table->timestamps();

            $table->index('passed', 'idx_screenings_passed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_screenings');
    }
};
