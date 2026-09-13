<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies TAQAT has engaged with formally — every recruitment case
 * and downstream contract hangs off one of these rows.
 *
 * `source_lead_id` preserves provenance when the Client came from a
 * conversion; the reverse pointer (leads.converted_client_id) is wired
 * in migration 100006 to close the FK cycle.
 *
 * Soft-unique on (company_name, country) — the same corporation may
 * exist as separate legal entities in different countries, but two
 * "ABC Technology / Jordan" rows are almost certainly a data-entry
 * mistake; ClientService warns before create, the DB is the last line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_number', 20)->unique();

            $table->string('company_name', 200);
            $table->string('company_website', 255)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('company_size', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();

            $table->string('tax_number', 50)->nullable();
            $table->string('payment_terms', 50)->nullable();
            $table->text('payment_terms_notes')->nullable();

            $table->string('status', 30)->default('active');

            // nullOnDelete: if the Account Manager user is later removed,
            // the Client stays but becomes unassigned rather than blocking
            // the delete.
            $table->foreignId('account_manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('source_lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_clients_status');
            $table->index('account_manager_id', 'idx_clients_account_manager');
            $table->index('source_lead_id', 'idx_clients_source_lead');
            $table->unique(['company_name', 'country'], 'idx_clients_company_country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
