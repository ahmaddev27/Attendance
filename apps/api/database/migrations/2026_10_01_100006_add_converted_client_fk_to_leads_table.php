<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the two-way FK between leads and clients:
 *
 *   leads.converted_client_id  -> clients.id  (added HERE)
 *   clients.source_lead_id     -> leads.id    (added in 100005)
 *
 * Split into a follow-up migration because the leads table is created
 * before clients exists (100003 -> 100005), so the FK on the leads
 * side has to be attached after both tables are in place. The column
 * itself already exists (nullable) from 100003.
 *
 * nullOnDelete: archiving a Client should NOT block the Lead's history
 * — leads keep their conversion timestamp and forget which client it
 * mapped to, which is the same permissive posture as
 * clients.source_lead_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('converted_client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['converted_client_id']);
        });
    }
};
