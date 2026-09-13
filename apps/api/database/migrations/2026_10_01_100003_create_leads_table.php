<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prospective clients captured by Sales before a formal engagement.
 * A Lead is never physically deleted after conversion — soft-deleted
 * at most — so the CRM keeps the full acquisition trail.
 *
 * `converted_client_id` is added as a plain nullable column here; the
 * FK back to clients.id is attached in migration 100006 to break the
 * two-way create cycle (clients also FKs source_lead_id -> leads.id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 20)->unique();

            $table->string('company_name', 200);
            $table->string('company_website', 255)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('company_size', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();

            $table->string('contact_person', 150)->nullable();
            $table->string('contact_position', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('linkedin_url', 255)->nullable();

            $table->string('source', 50);
            $table->string('status', 30)->default('new');

            // owner is a User (Sales rep) — not an Employee — because a
            // bootstrap sales admin may not yet have a linked Employee row.
            // Same argument as clients.account_manager_id.
            $table->foreignId('owner_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('expected_hiring_volume')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();

            // Populated by LeadConversionService when the Lead is turned
            // into a Client. Kept even if the Client is later archived.
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('converted_client_id')->nullable();

            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_leads_status');
            $table->index(['owner_id', 'status'], 'idx_leads_owner_status');
            $table->index('next_followup_at', 'idx_leads_next_followup');
            $table->index('source', 'idx_leads_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
