<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->string('color', 7)->default('#2678C4');
            $table->integer('sort_order')->default(0);

            // Marks which status(es) count as "finished" / "cancelled" —
            // TaskService reads these to decide when to stamp
            // completed_at, and TaskController::complete() resolves the
            // first is_done_state status (by sort_order) as its target.
            $table->boolean('is_done_state')->default(false);
            $table->boolean('is_cancelled_state')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_statuses');
    }
};
