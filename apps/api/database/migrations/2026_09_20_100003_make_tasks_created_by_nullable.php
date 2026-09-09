<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow tasks.created_by to be NULL so an admin user who isn't linked to
 * an Employee record can still create tasks (a legitimate scenario: the
 * bootstrap super-admin, integration bots, admins seeded before the
 * employee provision flow existed).
 *
 * Semantically: task ownership is expressed by `assigned_to`, not by
 * `created_by` — the creator field is just audit info. A null value
 * renders as "النظام" / "الأدمن" on the UI.
 *
 * The FK constraint stays intact (still points at employees.id when set),
 * only the NOT NULL is dropped. Existing rows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Backfill any newly-created NULLs to the smallest employee_id
        // before re-enforcing NOT NULL, so the rollback doesn't fail on
        // rows created after this migration ran.
        $fallback = \App\Models\Employee::query()->orderBy('id')->value('id');

        if ($fallback !== null) {
            \Illuminate\Support\Facades\DB::table('tasks')
                ->whereNull('created_by')
                ->update(['created_by' => $fallback]);
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }
};
