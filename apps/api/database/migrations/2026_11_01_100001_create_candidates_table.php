<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment Phase 2 — Candidate bank. One row per PERSON (not per
 * application): the same candidate may apply to multiple jobs over the
 * year, so job-specific state lives on `candidate_applications`
 * instead. See docs/recruitment/05-phase-2-plan.md §4.1 for the full
 * schema rationale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table): void {
            $table->id();
            // Human-readable sequence: CAN-YYYY-####. Not a surrogate key
            // — just an identifier admins can quote on paperwork.
            $table->string('candidate_number', 20)->unique();

            $table->string('full_name', 200);
            // email / phone are NOT unique at the column level — a
            // candidate without either is valid (walk-in), and the CSV
            // import does a soft dedup on them. Hard uniqueness here
            // would block the legitimate "re-add the same walk-in later".
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->string('portfolio_url', 255)->nullable();

            // Private-disk CV, served through a signed URL — same pattern
            // as leave attachments. Path stays server-side only.
            $table->string('resume_path', 500)->nullable();
            $table->timestamp('resume_uploaded_at')->nullable();

            $table->string('status', 30)->default('active');
            // 'active'|'blacklisted'|'placed'|'inactive'
            $table->string('source', 50)->nullable();
            // 'csv_import'|'manual'|'brightgaza'|'referral' — plain string
            // so adding a new source is a code-free config change.
            $table->string('source_reference', 100)->nullable();

            $table->string('headline', 200)->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->string('current_title', 150)->nullable();
            $table->string('current_company', 150)->nullable();
            $table->decimal('expected_salary_min', 10, 2)->nullable();
            $table->decimal('expected_salary_max', 10, 2)->nullable();
            $table->char('salary_currency', 3)->nullable()->default('USD');
            $table->string('availability', 50)->nullable();
            // 'immediate'|'2_weeks'|'1_month'|'negotiable'

            $table->json('skills')->nullable();
            $table->json('languages')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_candidates_status');
            $table->index('email', 'idx_candidates_email');
            $table->index('phone', 'idx_candidates_phone');
            $table->index('source', 'idx_candidates_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
