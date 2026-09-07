<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attaches every employee to a work schedule. Nullable rather than a hard
 * DB default: the "default seeded schedule" (AttendanceSeeder's "Standard"
 * schedule) doesn't exist until seed time, so its id can't be baked into
 * the column definition. AttendanceSeeder backfills any employee left with
 * a null work_schedule_id to the default schedule after creating it — see
 * the seeder for details and the final report for the coordinator note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('work_schedule_id')
                ->nullable()
                ->after('status')
                ->constrained('work_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_schedule_id');
        });
    }
};
