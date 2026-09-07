<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_number')->unique();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->nullable()->unique();
            $table->string('phone', 20)->nullable();

            $table->foreignId('position_id')->nullable()
                ->constrained('positions')
                ->nullOnDelete();

            $table->foreignId('department_id')->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->foreignId('team_id')->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            // Self-referential FK: the table exists by the time the
            // constraint is checked, so this is safe within the same
            // create statement.
            $table->foreignId('direct_manager_id')->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->enum('employment_type', ['full_time', 'part_time', 'contractor', 'intern']);
            $table->date('joining_date');
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('avatar_path')->nullable();
            $table->enum('status', ['active', 'inactive', 'on_leave', 'terminated'])->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('department_id');
            $table->index('direct_manager_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
