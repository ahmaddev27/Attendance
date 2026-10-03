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
 * Builds a Job on the default standard pipeline + its first stage —
 * mirrors the raw ::create pattern Phase 1 tests use, since there's no
 * JobRequirement factory in the repo yet.
 *
 * `$instance` is untyped because Pest wraps every test's `$this` in a
 * dynamic `P\...` proxy class, so a trait typehint rejects it even
 * though the trait IS mixed in.
 */
function makeJobOnStandardPipeline($instance): JobRequirement
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
        'title' => 'Hiring Drive '.random_int(100, 999),
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

test('attaching a candidate creates an application in the job\'s current stage', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobOnStandardPipeline($this);
    $candidate = Candidate::factory()->create();

    $response = $this->postJson("/api/jobs/{$job->id}/applications", [
        'candidate_id' => $candidate->id,
    ])->assertCreated();

    expect($response->json('data.application_number'))->toStartWith('APP-'.date('Y').'-');
    expect($response->json('data.status'))->toBe('applied');
    expect($response->json('data.current_stage.id'))->toBe($job->current_stage_id);
});

test('the UNIQUE constraint on (candidate_id, job_requirement_id) is enforced with a friendly 422', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobOnStandardPipeline($this);
    $candidate = Candidate::factory()->create();

    $this->postJson("/api/jobs/{$job->id}/applications", ['candidate_id' => $candidate->id])->assertCreated();

    $this->postJson("/api/jobs/{$job->id}/applications", ['candidate_id' => $candidate->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['candidate_id' => 'هذا المرشّح متقدّم على هذه الوظيفة بالفعل.']);

    expect(CandidateApplication::where('job_requirement_id', $job->id)->count())->toBe(1);
});

test('listing applications for a job honours the status filter', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobOnStandardPipeline($this);
    $stage = $job->current_stage_id;

    CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $stage,
        'status' => CandidateApplicationStatus::Applied->value,
    ]);
    CandidateApplication::factory()->for(Candidate::factory())->shortlisted()->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $stage,
    ]);

    $response = $this->getJson("/api/jobs/{$job->id}/applications?status=shortlisted")->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('shortlisted');
});

test('rejecting an application stamps the user, the reason and the stage code', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $job = makeJobOnStandardPipeline($this);
    $candidate = Candidate::factory()->create();

    $created = $this->postJson("/api/jobs/{$job->id}/applications", ['candidate_id' => $candidate->id])->assertCreated();
    $applicationId = $created->json('data.id');

    $this->postJson("/api/applications/{$applicationId}/reject", [
        'reason' => 'Not a technical fit for this role.',
    ])->assertOk();

    $application = CandidateApplication::find($applicationId);
    expect($application->status->value)->toBe('rejected');
    expect($application->rejected_by_user_id)->toBe($admin->id);
    expect($application->rejection_reason)->toBe('Not a technical fit for this role.');
    expect($application->rejection_stage_code)->not->toBeNull();
});

test('rejecting a terminal application returns 422 — no second-time rejection', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobOnStandardPipeline($this);

    $application = CandidateApplication::factory()
        ->for(Candidate::factory())
        ->rejected()
        ->create([
            'job_requirement_id' => $job->id,
            'current_stage_id' => $job->current_stage_id,
        ]);

    $this->postJson("/api/applications/{$application->id}/reject", ['reason' => 'again'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('withdrawing an application marks it withdrawn without needing a reason', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobOnStandardPipeline($this);
    $candidate = Candidate::factory()->create();

    $created = $this->postJson("/api/jobs/{$job->id}/applications", ['candidate_id' => $candidate->id])->assertCreated();
    $applicationId = $created->json('data.id');

    $this->postJson("/api/applications/{$applicationId}/withdraw")->assertOk();

    expect(CandidateApplication::find($applicationId)->status->value)->toBe('withdrawn');
});

test('non-manager cannot attach a candidate — view-jobs alone is not enough', function () {
    $this->actingAsUserWithPermissions(['view-jobs']);
    $job = makeJobOnStandardPipeline($this);
    $candidate = Candidate::factory()->create();

    $this->postJson("/api/jobs/{$job->id}/applications", ['candidate_id' => $candidate->id])
        ->assertForbidden();
});

test('viewing a candidate\'s applications requires view-candidates', function () {
    $this->actingAsRecruitmentAdmin();
    $job = makeJobOnStandardPipeline($this);
    $candidate = Candidate::factory()->create();
    $this->postJson("/api/jobs/{$job->id}/applications", ['candidate_id' => $candidate->id])->assertCreated();

    // Switch to a role without view-candidates — reads should bounce.
    $this->actingAsUserWithPermissions(['view-jobs']);

    $this->getJson("/api/candidates/{$candidate->id}/applications")->assertForbidden();
});
