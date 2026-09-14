<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The recruitment dedup policy for clients is "warn, then allow a forced
 * create" (ClientService::create, LeadConversionService). A hard UNIQUE on
 * (company_name, country) contradicted it: the forced create failed with a
 * 500, and so did re-creating a client whose namesake had been soft-deleted.
 * The service-level warning stays; the index becomes a plain lookup index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('idx_clients_company_country');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index(['company_name', 'country'], 'idx_clients_company_country');
        });
    }

    public function down(): void
    {
        // Fails once duplicates were force-created: those rows have to be
        // merged by hand before the unique index can come back.
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_company_country');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['company_name', 'country'], 'idx_clients_company_country');
        });
    }
};
