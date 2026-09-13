<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic "the business entity this task is about" pointer so the
 * generic Task engine can carry recruitment (and future) work items
 * without a dedicated FK per new entity type. entity_type is stored as
 * a short string (see App\Shared\Enums\TaskEntityType) rather than a
 * model class name so renaming/moving the Model class never rewrites
 * historical rows.
 *
 * Nullable + no FK: generic tasks with no entity (the pre-recruitment
 * status quo) keep working; the (entity_type, entity_id) index serves
 * both "which tasks are open on this job?" and "list every task on
 * this entity type" queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('entity_type', 50)->nullable()->after('assigned_to');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');

            $table->index(['entity_type', 'entity_id'], 'idx_tasks_entity');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tasks_entity');
            $table->dropColumn(['entity_id', 'entity_type']);
        });
    }
};
