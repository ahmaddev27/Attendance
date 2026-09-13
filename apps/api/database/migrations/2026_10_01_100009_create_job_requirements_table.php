<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single open position inside a RecruitmentCase — the entity the
 * Pipeline Engine walks stage-by-stage.
 *
 * stage_entered_at is deliberately separate from updated_at: SLA
 * scanning uses it to find "in-stage for > sla_hours" jobs, and it
 * only advances when the stage actually changes, whereas updated_at
 * shifts on every field edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 20)->unique();

            $table->foreignId('recruitment_case_id')
                ->constrained('recruitment_cases')
                ->restrictOnDelete();

            // Pipeline + current stage both restrictOnDelete: retiring
            // either while active jobs still reference them would leave
            // the job with no stage to advance to.
            $table->foreignId('pipeline_id')
                ->constrained('recruitment_pipelines')
                ->restrictOnDelete();

            $table->foreignId('current_stage_id')
                ->constrained('recruitment_pipeline_stages')
                ->restrictOnDelete();

            $table->foreignId('owner_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title', 200);
            $table->string('department', 100)->nullable();
            $table->unsignedSmallInteger('openings')->default(1);
            $table->string('employment_type', 30);
            $table->string('work_mode', 20);
            $table->string('location', 200)->nullable();

            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->char('salary_currency', 3)->nullable()->default('USD');

            $table->unsignedSmallInteger('required_experience_years')->nullable();
            $table->string('education_level', 50)->nullable();
            $table->json('required_skills')->nullable();
            $table->json('nice_to_have_skills')->nullable();
            $table->json('required_languages')->nullable();
            $table->text('description')->nullable();
            $table->text('responsibilities')->nullable();

            // Populated when the job crosses the 'publish' stage
            // (currently manual in Phase 1, BrightGaza API in Phase 4).
            $table->string('publication_url', 500)->nullable();
            $table->timestamp('published_at')->nullable();

            $table->date('application_deadline')->nullable();
            $table->date('target_start_date')->nullable();

            $table->string('status', 30)->default('draft');

            $table->timestamp('stage_entered_at');
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['recruitment_case_id', 'status'], 'idx_jobs_case_status');
            $table->index('owner_id', 'idx_jobs_owner');
            $table->index('current_stage_id', 'idx_jobs_current_stage');
            $table->index('status', 'idx_jobs_status');
            $table->index('stage_entered_at', 'idx_jobs_stage_entered');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requirements');
    }
};
