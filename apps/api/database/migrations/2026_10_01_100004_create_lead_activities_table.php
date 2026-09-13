<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every meaningful touch with a Lead (call, meeting, email, note) plus
 * system-generated entries (status_change, owner_change) so the Lead
 * detail timeline is a single source of truth.
 *
 * Cascade-deletes with the parent Lead — an activity has no meaning
 * without it. Users, on the other hand, are restricted: we need to
 * keep the log of who logged what even if a sales rep leaves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('type', 30);
            $table->string('subject', 200)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Timeline queries always order by (lead, most-recent-first);
            // this composite index serves both the lead filter and the sort.
            $table->index(['lead_id', 'occurred_at'], 'idx_activities_lead_time');
            $table->index('user_id', 'idx_activities_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
    }
};
