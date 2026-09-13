<?php

use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Shared\Enums\JobRequirementStatus;
use App\Shared\Enums\RecruitmentCaseStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * Every job needs a Case, and every advance/create needs the default
 * pipeline seeded — this shared helper keeps the arrange block short.
 */
function makeJobCase(int $ownerId): RecruitmentCase
{
    $client = Client::create([
        'client_number' => 'C-2026-'.random_int(4000, 4999),
        'company_name' => 'Job Client '.uniqid(),
        'account_manager_id' => $ownerId,
        'status' => 'active',
    ]);

    return RecruitmentCase::create([
        'case_number' => 'RC-2026-'.random_int(4000, 4999),
        'client_id' => $client->id,
        'title' => 'Case '.uniqid(),
        'owner_id' => $ownerId,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);
}

test('an admin can create a job requirement', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();

    $case = makeJobCase($admin->id);

    $response = $this->postJson('/api/jobs', [
        'recruitment_case_id' => $case->id,
        'owner_id' => $admin->id,
        'title' => 'Senior Engineer',
        'openings' => 3,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Senior Engineer')
        ->assertJsonPath('data.openings', 3);

    $this->assertDatabaseHas('job_requirements', ['title' => 'Senior Engineer']);
});

test('creating a job without manage-jobs permission is forbidden', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $case = makeJobCase($admin->id);

    $viewer = $this->actingAsUserWithPermissions(['view-jobs']);

    $response = $this->postJson('/api/jobs', [
        'recruitment_case_id' => $case->id,
        'owner_id' => $viewer->id,
        'title' => 'Nope',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
    ]);

    $response->assertForbidden();
});

test('creating a job with invalid employment_type is rejected', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $case = makeJobCase($admin->id);

    $response = $this->postJson('/api/jobs', [
        'recruitment_case_id' => $case->id,
        'owner_id' => $admin->id,
        'title' => 'Bad Type',
        'openings' => 1,
        'employment_type' => 'gig_work',
        'work_mode' => 'remote',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['employment_type']);
});

test('an admin can update a job requirement', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $case = makeJobCase($admin->id);

    $case = $case->fresh();

    $this->postJson('/api/jobs', [
        'recruitment_case_id' => $case->id,
        'owner_id' => $admin->id,
        'title' => 'Original',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
    ])->assertCreated();

    $job = JobRequirement::where('title', 'Original')->firstOrFail();

    $response = $this->patchJson("/api/jobs/{$job->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertOk()->assertJsonPath('data.title', 'Updated Title');
});

test('a closed (filled) job cannot be updated', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $firstStage = $pipeline->firstStage();
    $case = makeJobCase($admin->id);

    $job = JobRequirement::create([
        'job_number' => 'J-2026-4001',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $firstStage->id,
        'owner_id' => $admin->id,
        'title' => 'Filled',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'status' => JobRequirementStatus::Filled->value,
        'stage_entered_at' => now(),
        'completed_at' => now(),
    ]);

    $response = $this->patchJson("/api/jobs/{$job->id}", ['title' => 'Should Fail']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

test('an admin can soft-delete a job', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $case = makeJobCase($admin->id);

    $job = JobRequirement::create([
        'job_number' => 'J-2026-4002',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $admin->id,
        'title' => 'Trash',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $this->deleteJson("/api/jobs/{$job->id}")->assertNoContent();
    $this->assertSoftDeleted('job_requirements', ['id' => $job->id]);
});

test('listing jobs returns paginated rows', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $case = makeJobCase($admin->id);

    JobRequirement::create([
        'job_number' => 'J-2026-4010',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $admin->id,
        'title' => 'Job A',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    JobRequirement::create([
        'job_number' => 'J-2026-4011',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $admin->id,
        'title' => 'Job B',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $response = $this->getJson('/api/jobs');
    $response->assertOk()->assertJsonCount(2, 'data');
});

test('nested jobs for a case index endpoint scopes to that case', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $caseA = makeJobCase($admin->id);
    $caseB = makeJobCase($admin->id);

    JobRequirement::create([
        'job_number' => 'J-2026-4020',
        'recruitment_case_id' => $caseA->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $admin->id,
        'title' => 'In A',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    JobRequirement::create([
        'job_number' => 'J-2026-4021',
        'recruitment_case_id' => $caseB->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $admin->id,
        'title' => 'In B',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $response = $this->getJson("/api/recruitment-cases/{$caseA->id}/jobs");
    $response->assertOk()->assertJsonCount(1, 'data');
});
