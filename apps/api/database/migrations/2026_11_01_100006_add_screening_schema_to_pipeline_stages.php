<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment Phase 2 — screening schema per pipeline stage (D3). JSON
 * so admins can edit the fields without a migration; the service
 * validates `candidate_screenings.scorecard` answers against this
 * schema per field. Shape:
 *   {"fields":[{"key":"...","label":"...","type":"rating_1_5|number|select|text","weight":0.4,"options":[...]}],"pass_threshold":3.5}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_pipeline_stages', function (Blueprint $table): void {
            $table->json('screening_schema')->nullable()->after('requires_fields');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_pipeline_stages', function (Blueprint $table): void {
            $table->dropColumn('screening_schema');
        });
    }
};
