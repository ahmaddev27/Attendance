<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment Phase 2 — rich pivot joining a candidate to a specific job.
 * Carries its OWN current_stage_id (not the job's) so one candidate can
 * be in `screening` while a colleague on the same job already reached
 * `interviewing`. See docs/recruitment/05-phase-2-plan.md §2.3 + §4.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('application_number', 20)->unique();

            $table->foreignId('candidate_id')
                ->constrained('candidates')
                ->restrictOnDelete();
            $table->foreignId('job_requirement_id')
                ->constrained('job_requirements')
                ->restrictOnDelete();
            // Separate from job_requirements.current_stage_id by design
            // (D6 in the plan) — the application walks its own path.
            $table->foreignId('current_stage_id')
                ->constrained('recruitment_pipeline_stages')
                ->restrictOnDelete();

            $table->string('status', 30)->default('applied');
            // applied|in_screening|screened_in|screened_out|shortlisted
            //  |interviewing|client_review|offered|rejected|withdrawn|hired

            // 'manual'|'csv_import'|'brightgaza' — audit trail for how
            // the application entered the system.
            $table->string('source', 50);
            $table->timestamp('applied_at');

            // Shortlist is a FLAG on the application (D2 in the plan) —
            // "shortlisted" is a view over this column, not a separate
            // table. Keeps writes simple and the UI consistent.
            $table->boolean('is_shortlisted')->default(false);
            $table->timestamp('shortlisted_at')->nullable();
            $table->foreignId('shortlisted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('rejection_reason', 255)->nullable();
            // The stage code at the moment of rejection, for analytics
            // ("where do we lose candidates most often?"). Captured as a
            // string so a stage rename later doesn't rewrite history.
            $table->string('rejection_stage_code', 50)->nullable();

            $table->text('notes')->nullable();
            // Mirrors JobRequirement.stage_entered_at — the SLA scanner
            // compares against this to decide "this application has been
            // sitting in <stage> too long".
            $table->timestamp('stage_entered_at');

            $table->timestamps();
            $table->softDeletes();

            // Q6 locked (2026-10-01): a candidate may apply to a job
            // AT MOST once. Re-applications after a reject stay on the
            // same row via status transitions, not a second row.
            $table->unique(
                ['candidate_id', 'job_requirement_id'],
                'idx_applications_candidate_job',
            );
            $table->index(['job_requirement_id', 'status'], 'idx_applications_job_status');
            $table->index(['job_requirement_id', 'is_shortlisted'], 'idx_applications_shortlisted');
            $table->index('stage_entered_at', 'idx_applications_stage_entered');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_applications');
    }
};
