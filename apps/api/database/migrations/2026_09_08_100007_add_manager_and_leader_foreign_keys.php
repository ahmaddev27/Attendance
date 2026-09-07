<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Wires up the department manager and team leader FKs to `employees`
     * now that the table exists. These columns were created earlier
     * (without a constraint) to break the circular dependency between
     * `departments`/`teams` and `employees`.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')->on('employees')
                ->nullOnDelete();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('leader_id')
                ->references('id')->on('employees')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['leader_id']);
        });
    }
};
