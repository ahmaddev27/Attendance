<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Q5 — every Recruitment migration must roll back cleanly and re-apply
 * on the same database. Runs through the real migrator (not by calling
 * down() directly) so the migrations table stays truthful, which is also
 * what lets a failed assertion be repaired by the `finally` block.
 *
 * Phase 2 (`2026_11_01_1000*.php`) must roll back BEFORE Phase 1 because
 * `candidate_applications` FKs point at `job_requirements`; dropping the
 * tables in forward order would fail the FK constraint. The migrator
 * handles that naturally when the whole window is rolled back together.
 */
const RECRUITMENT_TABLES = [
    'recruitment_pipelines',
    'recruitment_pipeline_stages',
    'leads',
    'lead_activities',
    'clients',
    'client_contacts',
    'recruitment_cases',
    'job_requirements',
    // Phase 2 — Candidate bank + Interview workflow.
    'candidates',
    'candidate_applications',
    'candidate_screenings',
    'interviews',
    'interview_feedbacks',
];

/**
 * @return list<string>
 */
function recruitmentMigrationPaths(): array
{
    $phase1 = glob(database_path('migrations/2026_10_01_1000*.php')) ?: [];
    $phase2 = glob(database_path('migrations/2026_11_01_1000*.php')) ?: [];
    $paths = array_merge($phase1, $phase2);
    sort($paths);

    return $paths;
}

test('recruitment migrations roll back completely and re-apply cleanly', function () {
    $paths = recruitmentMigrationPaths();
    $names = array_map(fn (string $path) => basename($path, '.php'), $paths);

    // 11 Phase 1 + 8 Phase 2 (candidate_import_jobs added in Week 2).
    expect($paths)->toHaveCount(19);

    try {
        $this->artisan('migrate:rollback', ['--path' => $paths, '--realpath' => true])
            ->assertExitCode(0);

        foreach (RECRUITMENT_TABLES as $table) {
            expect(Schema::hasTable($table))->toBeFalse("{$table} should be dropped");
        }

        expect(Schema::hasColumns('tasks', ['entity_type', 'entity_id']))->toBeFalse()
            ->and(Schema::hasTable('tasks'))->toBeTrue()
            ->and(DB::table('migrations')->whereIn('migration', $names)->count())->toBe(0);

        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true])
            ->assertExitCode(0);

        foreach (RECRUITMENT_TABLES as $table) {
            expect(Schema::hasTable($table))->toBeTrue("{$table} should be recreated");
        }

        expect(Schema::hasColumns('tasks', ['entity_type', 'entity_id']))->toBeTrue()
            ->and(Schema::hasColumns('leads', ['converted_client_id', 'lead_number']))->toBeTrue()
            ->and(DB::table('migrations')->whereIn('migration', $names)->count())->toBe(19);

        // The reference-data migration re-seeds on the way back up.
        $pipelineId = DB::table('recruitment_pipelines')->where('code', 'standard')->value('id');

        expect($pipelineId)->not->toBeNull()
            ->and(DB::table('recruitment_pipeline_stages')->where('pipeline_id', $pipelineId)->count())->toBe(10)
            ->and(DB::table('permissions')->where('name', 'manage-recruitment-pipelines')->exists())->toBeTrue();
    } finally {
        // Leave the schema whole for the rest of the suite even when an
        // assertion above failed half-way; a no-op when nothing is pending.
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true]);
    }
});

test('the reference-data migration never overwrites an existing standard pipeline', function () {
    $pipelineId = DB::table('recruitment_pipelines')->where('code', 'standard')->value('id');
    DB::table('recruitment_pipeline_stages')
        ->where('pipeline_id', $pipelineId)
        ->where('code', 'publish')
        ->update(['sla_hours' => 6]);

    $migration = require database_path('migrations/2026_10_01_100011_seed_recruitment_reference_data.php');
    $migration->up();

    expect(DB::table('recruitment_pipeline_stages')->where('pipeline_id', $pipelineId)->where('code', 'publish')->value('sla_hours'))
        ->toBe(6)
        ->and(DB::table('recruitment_pipelines')->where('code', 'standard')->count())->toBe(1);
});
