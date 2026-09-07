<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');

            // The spec's closed action list is extended with 'updated' —
            // a catch-all for meaningful-but-not-otherwise-named field
            // changes (due_date, progress_percent) that TaskService::update()
            // is asked to log but that don't have a dedicated action of
            // their own. See TaskAction for the full list and rationale.
            $table->enum('action', [
                'created',
                'assigned',
                'unassigned',
                'status_changed',
                'priority_changed',
                'commented',
                'attached_file',
                'completed',
                'deleted',
                'restored',
                'updated',
            ]);

            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();

            // Insert-only append log: no updated_at, created_at only.
            $table->timestamp('created_at')->nullable();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_history');
    }
};
