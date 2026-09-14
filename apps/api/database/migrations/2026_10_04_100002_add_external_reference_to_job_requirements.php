<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs pulled from an outside board (BrightGaza today) keep a reference to
 * where they came from. The unique (external_source, external_id) pair is
 * what stops a second pull from creating duplicates; external_payload keeps
 * every field the board returned, including those TAQAT has no column for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_requirements', function (Blueprint $table) {
            $table->string('external_source', 30)->nullable()->after('status');
            $table->string('external_id', 64)->nullable()->after('external_source');
            $table->string('external_status', 50)->nullable()->after('external_id');
            $table->json('external_payload')->nullable()->after('external_status');
            $table->timestamp('external_synced_at')->nullable()->after('external_payload');

            $table->unique(['external_source', 'external_id'], 'idx_jobs_external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('job_requirements', function (Blueprint $table) {
            $table->dropUnique('idx_jobs_external_reference');
            $table->dropColumn([
                'external_source',
                'external_id',
                'external_status',
                'external_payload',
                'external_synced_at',
            ]);
        });
    }
};
