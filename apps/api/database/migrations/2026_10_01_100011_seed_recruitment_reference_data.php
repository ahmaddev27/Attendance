<?php

use Database\Seeders\RecruitmentPermissionSeeder;
use Database\Seeders\RecruitmentPipelineSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ships the reference data Recruitment cannot run without: the 16
 * permissions (granted to super-admin when that role exists) and the
 * standard hiring pipeline that JobRequirementService falls back to.
 *
 * Deploys only run `migrate --force` and nobody seeds the production
 * database by hand, so a migration is the one path that reaches it.
 * The seeders are reused rather than copied so the permission list and
 * stage template have a single source of truth.
 *
 * The pipeline is only created when no `standard` pipeline exists yet:
 * the seeder upserts stages, and re-running it over a pipeline an admin
 * already tuned would silently overwrite their SLAs and owners.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RecruitmentPermissionSeeder())->run();

        if (! DB::table('recruitment_pipelines')->where('code', 'standard')->exists()) {
            (new RecruitmentPipelineSeeder())->run();
        }
    }

    public function down(): void
    {
        // Intentionally empty. Rolling further back drops the pipeline
        // tables with their rows; the permission rows are harmless on
        // their own, and deleting them would strip role grants an admin
        // made in the meantime.
    }
};
