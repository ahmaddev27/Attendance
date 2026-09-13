<?php

use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\RecruitmentPipeline;
use App\Models\RecruitmentPipelineStage;
use App\Shared\Enums\RecruitmentCaseStatus;
use App\Shared\Enums\StageOwnerRule;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('an admin can create a pipeline', function () {
    $this->actingAsRecruitmentAdmin();

    $response = $this->postJson('/api/recruitment-pipelines', [
        'name' => 'Fast Track',
        'code' => 'fast-track',
        'is_default' => false,
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'fast-track');

    $this->assertDatabaseHas('recruitment_pipelines', ['code' => 'fast-track']);
});

test('a user without manage-recruitment-pipelines permission cannot list pipelines', function () {
    $this->actingAsUserWithPermissions(['view-jobs']);

    $this->getJson('/api/recruitment-pipelines')->assertForbidden();
});

test('an admin can add a stage to a pipeline', function () {
    $this->actingAsRecruitmentAdmin();

    $pipeline = RecruitmentPipeline::create([
        'name' => 'Simple',
        'code' => 'simple',
        'is_active' => true,
    ]);

    $response = $this->postJson("/api/recruitment-pipelines/{$pipeline->id}/stages", [
        'code' => 'kickoff',
        'name' => 'Kickoff',
        'owner_rule_type' => StageOwnerRule::JobOwner->value,
    ]);

    $response->assertCreated()->assertJsonPath('data.code', 'kickoff');
});

test('reorder replays the incoming ids into display_order starting at 1', function () {
    $this->actingAsRecruitmentAdmin();

    $pipeline = RecruitmentPipeline::create([
        'name' => 'Reorder Me',
        'code' => 'reorder-me',
        'is_active' => true,
    ]);

    $s1 = RecruitmentPipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'display_order' => 1,
        'code' => 'a',
        'name' => 'Alpha',
        'owner_rule_type' => StageOwnerRule::None->value,
    ]);

    $s2 = RecruitmentPipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'display_order' => 2,
        'code' => 'b',
        'name' => 'Beta',
        'owner_rule_type' => StageOwnerRule::None->value,
    ]);

    $s3 = RecruitmentPipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'display_order' => 3,
        'code' => 'c',
        'name' => 'Gamma',
        'owner_rule_type' => StageOwnerRule::None->value,
    ]);

    $response = $this->postJson("/api/recruitment-pipelines/{$pipeline->id}/stages/reorder", [
        'stage_ids' => [$s3->id, $s1->id, $s2->id],
    ]);

    $response->assertOk();

    expect($s3->fresh()->display_order)->toBe(1)
        ->and($s1->fresh()->display_order)->toBe(2)
        ->and($s2->fresh()->display_order)->toBe(3);
});

test('reorder with an incomplete stage list is rejected', function () {
    $this->actingAsRecruitmentAdmin();

    $pipeline = RecruitmentPipeline::create([
        'name' => 'Incomplete',
        'code' => 'incomplete-list',
        'is_active' => true,
    ]);

    $s1 = RecruitmentPipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'display_order' => 1,
        'code' => 'x',
        'name' => 'X',
        'owner_rule_type' => StageOwnerRule::None->value,
    ]);

    RecruitmentPipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'display_order' => 2,
        'code' => 'y',
        'name' => 'Y',
        'owner_rule_type' => StageOwnerRule::None->value,
    ]);

    $response = $this->postJson("/api/recruitment-pipelines/{$pipeline->id}/stages/reorder", [
        'stage_ids' => [$s1->id],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['stage_ids']);
});

test('deleting a stage that has jobs on it is rejected', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $pipeline = RecruitmentPipeline::create([
        'name' => 'Blocked Delete',
        'code' => 'blocked-delete',
        'is_active' => true,
    ]);

    $stage = RecruitmentPipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'display_order' => 1,
        'code' => 'sole',
        'name' => 'Sole',
        'owner_rule_type' => StageOwnerRule::JobOwner->value,
    ]);

    $client = Client::create([
        'client_number' => 'C-2026-2001',
        'company_name' => 'Blocking Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-2001',
        'client_id' => $client->id,
        'title' => 'Blocking Case',
        'owner_id' => $admin->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    JobRequirement::create([
        'job_number' => 'J-2026-2001',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $stage->id,
        'owner_id' => $admin->id,
        'title' => 'On Stage',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $response = $this->deleteJson("/api/recruitment-pipelines/{$pipeline->id}/stages/{$stage->id}");

    $response->assertUnprocessable()->assertJsonValidationErrors(['stage']);
});
