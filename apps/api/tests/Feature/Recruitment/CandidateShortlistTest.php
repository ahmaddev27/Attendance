<?php

declare(strict_types=1);

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Shared\Enums\CandidateApplicationStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * Local mirror of the Pest helper from CandidateApplicationTest — Pest
 * does not share top-level functions across files, so each test file
 * defines its own copy.
 */
function makeJobForShortlist($instance): JobRequirement
{
    $pipeline = $instance->seedStandardPipeline();
    $firstStage = $pipeline->stages()->orderBy('display_order')->first();

    $owner = User::factory()->create();
    $client = Client::create([
        'client_number' => 'C-'.date('Y').'-'.random_int(1000, 9999),
        'company_name' => 'ACME '.random_int(100, 999),
        'country' => 'Palestine',
        'city' => 'Gaza',
        'owner_id' => $owner->id,
    ]);
    $case = RecruitmentCase::create([
        'case_number' => 'RC-'.date('Y').'-'.random_int(1000, 9999),
        'client_id' => $client->id,
        'title' => 'Hiring Drive',
        'owner_id' => $owner->id,
    ]);

    return JobRequirement::create([
        'job_number' => 'J-'.date('Y').'-'.random_int(1000, 9999),
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $firstStage->id,
        'owner_id' => $owner->id,
        'title' => 'Senior Backend',
        'employment_type' => 'full_time',
        'work_mode' => 'onsite',
        'openings' => 2,
        'status' => 'active',
        'stage_entered_at' => now(),
    ]);
}

test('adding to shortlist stamps the flag, timestamp, user, and mirrors status', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $job = makeJobForShortlist($this);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/shortlist")
        ->assertOk()
        ->assertJsonPath('data.is_shortlisted', true);

    $fresh = $application->fresh();
    expect($fresh->is_shortlisted)->toBeTrue();
    expect($fresh->shortlisted_at)->not->toBeNull();
    expect($fresh->shortlisted_by_user_id)->toBe($admin->id);
    expect($fresh->status->value)->toBe(CandidateApplicationStatus::Shortlisted->value);
});

test('adding to shortlist a second time is idempotent', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobForShortlist($this);
    $application = CandidateApplication::factory()->for(Candidate::factory())->shortlisted()->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);
    $firstStamp = $application->fresh()->shortlisted_at;

    $this->postJson("/api/applications/{$application->id}/shortlist")->assertOk();

    expect($application->fresh()->shortlisted_at->toIso8601String())
        ->toBe($firstStamp->toIso8601String());
});

test('removing from shortlist clears the flag and reverts the mirrored status', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobForShortlist($this);
    $application = CandidateApplication::factory()->for(Candidate::factory())->shortlisted()->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->deleteJson("/api/applications/{$application->id}/shortlist")
        ->assertOk()
        ->assertJsonPath('data.is_shortlisted', false);

    $fresh = $application->fresh();
    expect($fresh->is_shortlisted)->toBeFalse();
    expect($fresh->shortlisted_at)->toBeNull();
    expect($fresh->status->value)->toBe(CandidateApplicationStatus::Applied->value);
});

test('shortlisting a terminal application is rejected', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobForShortlist($this);
    $application = CandidateApplication::factory()->for(Candidate::factory())->rejected()->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/shortlist")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('the per-job shortlist endpoint lists only flagged rows', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobForShortlist($this);

    CandidateApplication::factory()->for(Candidate::factory())->shortlisted()->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);
    CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $response = $this->getJson("/api/jobs/{$job->id}/shortlist")->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.is_shortlisted'))->toBeTrue();
});

test('shortlist toggle requires shortlist-candidates — manage-candidates alone is NOT enough', function () {
    $this->actingAsUserWithPermissions(['view-jobs', 'view-candidates', 'manage-candidates']);
    $job = makeJobForShortlist($this);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/shortlist")->assertForbidden();
});
