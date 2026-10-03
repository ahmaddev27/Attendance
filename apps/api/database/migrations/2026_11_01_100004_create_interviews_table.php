<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment Phase 2 — one row per scheduled interview. `kind`
 * distinguishes internal (TAQAT panel) from client interviews — the
 * plan deliberately uses one table with a polymorphic kind column
 * instead of splitting (Decision D4), since the schema is identical
 * and the only real difference is who attends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table): void {
            $table->id();
            $table->string('interview_number', 20)->unique();

            $table->foreignId('application_id')
                ->constrained('candidate_applications')
                ->restrictOnDelete();
            $table->string('kind', 20); // 'internal' | 'client'

            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('timezone', 50)->default('Asia/Gaza');

            $table->string('location', 255)->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->text('meeting_notes')->nullable();

            $table->string('status', 30)->default('scheduled');
            // 'scheduled'|'completed'|'cancelled'|'no_show'|'rescheduled'
            $table->string('cancelled_reason', 255)->nullable();
            // Self-reference so a reschedule links to the old row for
            // audit without losing history.
            $table->foreignId('rescheduled_from_id')
                ->nullable()
                ->constrained('interviews')
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('application_id', 'idx_interviews_application');
            $table->index('scheduled_at', 'idx_interviews_scheduled');
            $table->index(['kind', 'status'], 'idx_interviews_kind_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
