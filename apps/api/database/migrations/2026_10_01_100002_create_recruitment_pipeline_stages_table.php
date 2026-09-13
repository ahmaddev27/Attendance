<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordered stages inside a recruitment pipeline. `owner_rule_type`
 * decides how the next assignee is resolved at transition time:
 *
 *   role                  -> first user carrying permission (owner_rule_value)
 *   specific              -> user id (owner_rule_value cast to int)
 *   case_owner            -> the RecruitmentCase's owner
 *   job_owner             -> the JobRequirement's owner
 *   previous_stage_owner  -> whoever handled the immediately previous
 *                            stage's auto-task
 *   none                  -> no owner (terminal stages like Hired/Cancelled)
 *
 * Kept as VARCHAR rather than an enum so a new rule type can be added
 * without a schema migration. See PipelineTaskGeneratorService for the
 * dispatch table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_pipeline_stages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pipeline_id')
                ->constrained('recruitment_pipelines')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('display_order');
            $table->string('code', 50);
            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->string('owner_rule_type', 30);
            $table->string('owner_rule_value', 100)->nullable();

            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->boolean('auto_generate_task')->default(true);
            $table->string('task_title_template', 200)->nullable();
            $table->string('task_priority', 20)->nullable();

            // Field names on job_requirements that must be non-null before
            // the job can leave this stage. Consumed by
            // JobRequirementService::advanceStage() at transition time.
            $table->json('requires_fields')->nullable();
            $table->boolean('is_terminal')->default(false);

            $table->timestamps();

            // A stage's code is unique inside its pipeline (different
            // pipelines may share a 'screening' code); ditto display_order
            // — the reorder endpoint updates all rows in one transaction.
            $table->unique(['pipeline_id', 'code'], 'idx_stages_pipeline_code');
            $table->unique(['pipeline_id', 'display_order'], 'idx_stages_pipeline_order');
            $table->index('pipeline_id', 'idx_stages_pipeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_pipeline_stages');
    }
};
