<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            // Self-referential FK: the table already exists by the time
            // this constraint is checked, same pattern as
            // employees.direct_manager_id. Cascades on delete so removing
            // a parent task also removes its subtasks rather than
            // orphaning them.
            $table->foreignId('parent_task_id')->nullable()
                ->constrained('tasks')
                ->cascadeOnDelete();

            $table->string('title', 200);
            $table->text('description')->nullable();

            // restrictOnDelete: a status/priority in use by a task cannot
            // be removed outright — retire it instead. Mirrors the
            // leave_types <-> leave_requests relationship in the Leaves
            // module.
            $table->foreignId('status_id')->constrained('task_statuses')->restrictOnDelete();
            $table->foreignId('priority_id')->constrained('task_priorities')->restrictOnDelete();

            $table->foreignId('created_by')->constrained('employees')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->restrictOnDelete();

            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->decimal('actual_hours', 5, 2)->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('assigned_to');
            $table->index('status_id');
            $table->index('priority_id');
            $table->index('due_date');
            $table->index('parent_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
