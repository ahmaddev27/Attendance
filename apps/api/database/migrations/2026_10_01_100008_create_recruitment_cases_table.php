<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One hiring engagement / campaign for a single Client. Bundles the
 * job requirements that were requested together (e.g. "Q1 2026 Remote
 * Push: 5 devs + 3 designers"), so a case can be closed as a whole and
 * its KPIs measured together.
 *
 * client_id restrictOnDelete: a Client with historical cases must be
 * archived (status = terminated), never hard-deleted, same policy as
 * request_types <-> requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 20)->unique();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->restrictOnDelete();

            $table->foreignId('source_lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete();

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->foreignId('owner_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('active');

            $table->unsignedSmallInteger('target_hires')->nullable();
            $table->date('started_at')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status'], 'idx_cases_client_status');
            $table->index('owner_id', 'idx_cases_owner');
            $table->index('deadline', 'idx_cases_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_cases');
    }
};
