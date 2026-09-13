<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Templates that group ordered stages a JobRequirement moves through.
 * One row here == one reusable pipeline definition; the actual stages
 * live in recruitment_pipeline_stages. `code` is the stable programmatic
 * identifier (referenced by seeders/tests); `name` is the human-facing
 * label editable from the admin UI. Exactly one row should carry
 * is_default = true — enforced softly in the service layer, not by a
 * partial unique index (portability across MySQL/SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'idx_pipelines_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_pipelines');
    }
};
