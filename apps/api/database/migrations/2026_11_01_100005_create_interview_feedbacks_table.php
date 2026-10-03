<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment Phase 2 — one row per interviewer PER interview (D5).
 * Keeping each interviewer's feedback as its own row lets the service
 * compute `average_score` with a straight SQL GROUP BY and lets the
 * audit log attribute every edit to the interviewer who made it —
 * both impossible if all three panelists shared a JSON blob on the
 * interview row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')
                ->constrained('interviews')
                ->cascadeOnDelete();
            $table->foreignId('interviewer_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Answers keyed by the stage's screening schema; same shape
            // as CandidateScreening.scorecard. Phase 2 uses the stage
            // schema for interviews too; a dedicated interview schema is
            // a Phase 3 refinement.
            $table->json('scorecard');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('recommendation', 20);
            // 'strong_hire'|'hire'|'maybe'|'no_hire'
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('submitted_at');
            $table->timestamps();

            // One feedback per interviewer per interview — a second
            // submission edits the same row (handled in service).
            $table->unique(
                ['interview_id', 'interviewer_user_id'],
                'idx_feedback_interview_interviewer',
            );
            $table->index('recommendation', 'idx_feedbacks_recommendation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_feedbacks');
    }
};
