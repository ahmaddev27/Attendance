<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');

            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();

            $table->string('check_in_ip', 45)->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->foreignId('check_in_device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();

            $table->string('check_out_ip', 45)->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->foreignId('check_out_device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();

            $table->integer('total_minutes')->nullable();
            $table->integer('late_minutes')->nullable();
            $table->integer('early_leave_minutes')->nullable();
            $table->integer('overtime_minutes')->default(0);

            $table->enum('status', [
                'present',
                'late',
                'early_leave',
                'absent',
                'on_leave',
                'holiday',
                'weekend',
                'remote',
                'business_mission',
            ])->default('absent');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
            $table->index('employee_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
