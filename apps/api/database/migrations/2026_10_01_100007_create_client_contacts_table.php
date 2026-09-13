<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multiple people we deal with at a client company (HR, CTO, CFO).
 * `is_primary` is enforced as a single-row-per-client invariant in
 * ClientContactService::setPrimary (a partial unique WHERE is_primary
 * is portable to MySQL 8+ but not to SQLite; the service keeps both
 * runtimes honest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            $table->string('full_name', 150);
            $table->string('position', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('client_id', 'idx_contacts_client');
            $table->index(['client_id', 'is_primary'], 'idx_contacts_client_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
