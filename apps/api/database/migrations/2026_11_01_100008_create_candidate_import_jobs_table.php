<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment Phase 2 — persistent tracker for every bulk candidate
 * CSV/XLSX import. Q3 (locked 2026-10-01) chose a durable table over
 * TTL cache so the uploader can poll progress after a page refresh and
 * so admins keep an audit trail of who imported what and when.
 *
 * The row is created in `pending` by the controller, flipped to
 * `processing` the moment the queue worker picks it up, and lands on
 * `completed_with_errors` (partial or clean success) or `failed`
 * (catastrophic — no row processed). Counters are the summary surface
 * for the status endpoint; per-row errors live in `errors` JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_import_jobs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('job_requirement_id')
                ->constrained('job_requirements')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Original filename the uploader chose — surfaced in the UI
            // so they can tell two sibling imports apart.
            $table->string('uploaded_filename', 255);
            // Server-side stored path on the `local` disk, never exposed
            // via API Resource (plan §13 — repo is public, uploaded files
            // may carry PII). Reserved for the worker's re-read.
            $table->string('storage_path', 500);

            // Row counters: totals are observed before processing starts,
            // the rest are incremented as rows commit. All zero on fresh
            // insert so a status check mid-flight still returns valid JSON.
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_candidates')->default(0);
            $table->unsignedInteger('reused_candidates')->default(0);
            $table->unsignedInteger('created_applications')->default(0);
            $table->unsignedInteger('skipped_duplicates')->default(0);

            $table->string('status', 30)->default('pending');
            // 'pending'|'processing'|'completed_with_errors'|'failed'

            // Per-row validation / processing errors, same shape as the
            // dry-run response: [{row, field, message}, ...]. Capped at
            // ~500 entries by the service so a malformed 2k-row file
            // can't blow the JSON column.
            $table->json('errors')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['job_requirement_id', 'status'], 'idx_imports_job_status');
            $table->index('uploaded_by_user_id', 'idx_imports_uploader');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_import_jobs');
    }
};
