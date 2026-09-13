<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Shared\Enums\RecruitmentCaseStatus;
use App\Shared\Enums\TaskEntityType;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * The auto-task generator needs at least one TaskStatus and TaskPriority
 * row seeded — otherwise it silently no-ops. Kept as a tiny helper so
 * every scenario that expects a task can call it explicitly.
 */
function seedTaskLookups(): void
{
    TaskStatus::factory()->create(['sort_order' => 1, 'code' => 'to_do_'.uniqid()]);
    TaskPriority::factory()->create(['sort_order' => 1, 'code' => 'normal_'.uniqid()]);
    TaskPriority::factory()->create(['sort_order' => 2, 'code' => 'high']);
}

function makeStageAdvanceCase(int $ownerId): RecruitmentCase
{
    $client = Client::create([
        'client_number' => 'C-2026-'.random_int(3000, 3999),
        'company_name' => 'Stage Client '.uniqid(),
        'account_manager_id' => $ownerId,
        'status' => 'active',
    ]);

    return RecruitmentCase::create([
        'case_number' => 'RC-2026-'.random_int(3000, 3999),
        'client_id' => $client->id,
        'title' => 'Stage Case '.uniqid(),
        'owner_id' => $ownerId,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);
}

test('advancing a job to the next stage without target_stage_id happy path', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    seedTaskLookups();

    $case = makeStageAdvanceCase($admin->id);
    $first = $pipeline->stages->firstWhere('code', 'new');

    $job = JobRequirement::create([
        'job_number' => 'J-2026-3001',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $first->id,
        'owner_id' => $admin->id,
        'title' => 'Auto Advance',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now()->subHour(),
    ]);

    $response = $this->postJson("/api/jobs/{$job->id}/advance-stage", []);

    $response->assertOk();

    $next = $pipeline->stages->firstWhere('code', 'publish');
    expect($response->json('data.current_stage_id'))->toBe($next->id);
});

test('advancing into publish without publication_url is rejected by requires_fields gate', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    seedTaskLookups();

    $case = makeStageAdvanceCase($admin->id);
    $publish = $pipeline->stages->firstWhere('code', 'publish');
    $receiving = $pipeline->stages->firstWhere('code', 'receiving_apps');

    $job = JobRequirement::create([
        'job_number' => 'J-2026-3002',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $publish->id,
        'owner_id' => $admin->id,
        'title' => 'Needs URL',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $response = $this->postJson("/api/jobs/{$job->id}/advance-stage", [
        'target_stage_id' => $receiving->id,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['fields']);
});

test('providing publication_url in fields payload passes the requires_fields gate', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    seedTaskLookups();

    $case = makeStageAdvanceCase($admin->id);
    $publish = $pipeline->stages->firstWhere('code', 'publish');
    $receiving = $pipeline->stages->firstWhere('code', 'receiving_apps');

    $job = JobRequirement::create([
        'job_number' => 'J-2026-3003',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $publish->id,
        'owner_id' => $admin->id,
        'title' => 'With URL',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $response = $this->postJson("/api/jobs/{$job->id}/advance-stage", [
        'target_stage_id' => $receiving->id,
        'fields' => ['publication_url' => 'https://brightgaza.jo/job/1'],
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('job_requirements', [
        'id' => $job->id,
        'publication_url' => 'https://brightgaza.jo/job/1',
    ]);
});

test('advancing a job already on a terminal stage is rejected', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    seedTaskLookups();

    $case = makeStageAdvanceCase($admin->id);
    $hired = $pipeline->stages->firstWhere('code', 'hired');

    $job = JobRequirement::create([
        'job_number' => 'J-2026-3004',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $hired->id,
        'owner_id' => $admin->id,
        'title' => 'Terminal',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $response = $this->postJson("/api/jobs/{$job->id}/advance-stage", []);

    $response->assertUnprocessable()->assertJsonValidationErrors(['current_stage']);
});

test('advancing into a stage with auto_generate_task creates a Task pointing at the job', function () {
    $this->seedRecruitmentPermissions();
    $pipeline = $this->seedStandardPipeline();
    seedTaskLookups();

    // Publisher user needs an Employee row so the generated Task has a
    // valid assigned_to FK — the generator skips task creation without it.
    $employee = Employee::factory()->create();
    $publisher = User::factory()->create(['employee_id' => $employee->id]);
    $publisher->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('publish-jobs', 'web'));

    // The actor advances stages — they need advance-job-stage but NOT
    // publish-jobs (otherwise the role-based owner resolver would prefer
    // whichever user id sorts first, defeating the fixture).
    $mover = $this->actingAsUserWithPermissions(['view-jobs', 'manage-jobs', 'advance-job-stage']);

    $case = makeStageAdvanceCase($mover->id);
    $newStage = $pipeline->stages->firstWhere('code', 'new');

    $job = JobRequirement::create([
        'job_number' => 'J-2026-3005',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $newStage->id,
        'owner_id' => $mover->id,
        'title' => 'Task Gen Job',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $this->postJson("/api/jobs/{$job->id}/advance-stage", [])->assertOk();

    $this->assertDatabaseHas('tasks', [
        'entity_type' => TaskEntityType::JobRequirement->value,
        'entity_id' => $job->id,
        'assigned_to' => $employee->id,
    ]);
});

test('a user without advance-job-stage permission is forbidden', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $case = makeStageAdvanceCase($admin->id);

    $job = JobRequirement::create([
        'job_number' => 'J-2026-3006',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $admin->id,
        'title' => 'RBAC Test',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    // Log in as a user that has view/manage-jobs but NOT advance-job-stage.
    $this->actingAsUserWithPermissions(['view-jobs', 'manage-jobs']);

    $this->postJson("/api/jobs/{$job->id}/advance-stage", [])->assertForbidden();
});

test('advancing to a stage in a different pipeline is rejected', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    seedTaskLookups();

    // Second pipeline with its own first stage.
    $alt = \App\Models\RecruitmentPipeline::create([
        'name' => 'Alt Pipeline',
        'code' => 'alt',
        'is_default' => false,
        'is_active' => true,
    ]);

    $altStage = \App\Models\RecruitmentPipelineStage::create([
        'pipeline_id' => $alt->id,
        'display_order' => 1,
        'code' => 'alt_stage',
        'name' => 'Alt Stage',
        'owner_rule_type' => \App\Shared\Enums\StageOwnerRule::None->value,
    ]);

    $case = makeStageAdvanceCase($admin->id);

    $job = JobRequirement::create([
        'job_number' => 'J-2026-3007',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $admin->id,
        'title' => 'Wrong Pipeline',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $response = $this->postJson("/api/jobs/{$job->id}/advance-stage", [
        'target_stage_id' => $altStage->id,
    ]);

    // stageOrFail scopes by (pipeline_id, id) so a stage in a different
    // pipeline surfaces as 404 rather than a 422 — the service's
    // downstream cross-pipeline guard never fires.
    $response->assertNotFound();
});
