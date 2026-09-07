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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 20)->nullable()->unique();

            $table->foreignId('parent_id')->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            // `employees` does not exist yet at this point in the migration
            // sequence, so the manager_id FK is added later once it does
            // (see 2026_09_08_100007_add_manager_and_leader_foreign_keys.php).
            $table->unsignedBigInteger('manager_id')->nullable();

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('parent_id');
            $table->index('manager_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
