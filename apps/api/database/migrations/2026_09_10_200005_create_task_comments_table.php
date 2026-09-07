<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Self-referential FK for reply threading — same
            // already-exists-by-constraint-time reasoning as
            // tasks.parent_task_id.
            $table->foreignId('parent_id')->nullable()
                ->constrained('task_comments')
                ->cascadeOnDelete();

            $table->text('body');

            // Array of mentioned user_ids, resolved client-side before the
            // comment is submitted (see TaskCommentService).
            $table->json('mentions')->nullable();

            $table->timestamp('edited_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
