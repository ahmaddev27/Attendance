<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('timezone', 64)->default('Asia/Amman');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->decimal('min_hours_per_day', 4, 2)->default(8.00);
            $table->unsignedInteger('grace_late_minutes')->default(15);
            $table->unsignedInteger('grace_early_leave_minutes')->default(15);
            $table->json('workdays');
            $table->boolean('is_flexible')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
