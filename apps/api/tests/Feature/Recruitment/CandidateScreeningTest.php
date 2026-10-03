<?php

declare(strict_types=1);

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\CandidateScreening;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\RecruitmentPipelineStage;
use App\Models\User;
use App\Shared\Enums\CandidateApplicationStatus;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

function seedStageWithSchema($instance, array $schema): array
{
    $pipeline = $instance->seedStandardPipeline();
    $stage = $pipeline->stages()->orderBy('display_order')->first();
    // Patch the first stage with the screening schema we want to test
    // against — the plan ships a default schema on the `screening`
    // stage via migration 100008, but Phase 2 Week 1 only landed the
    // column so we set the schema here.
    DB::table('recruitment_pipeline_stages')
        ->where('id', $stage->id)
        ->update(['screening_schema' => json_encode($schema)]);

    return ['pipeline' => $pipeline, 'stage' => $stage->fresh()];
}

function makeJobOnStage(RecruitmentPipelineStage $stage): JobRequirement
{
    $owner = User::factory()->create();
    $client = Client::create([
        'client_number' => 'C-'.date('Y').'-'.random_int(1000, 9999),
        'company_name' => 'Hirer',
        'country' => 'Palestine',
        'city' => 'Gaza',
        'owner_id' => $owner->id,
    ]);
    $case = RecruitmentCase::create([
        'case_number' => 'RC-'.date('Y').'-'.random_int(1000, 9999),
        'client_id' => $client->id,
        'title' => 'Build',
        'owner_id' => $owner->id,
    ]);

    return JobRequirement::create([
        'job_number' => 'J-'.date('Y').'-'.random_int(1000, 9999),
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $stage->pipeline_id,
        'current_stage_id' => $stage->id,
        'owner_id' => $owner->id,
        'title' => 'Dev',
        'employment_type' => 'full_time',
        'work_mode' => 'onsite',
        'openings' => 1,
        'status' => 'active',
        'stage_entered_at' => now(),
    ]);
}

/**
 * @var array<string, mixed>
 */
const DEFAULT_SCREENING_SCHEMA = [
    'fields' => [
        ['key' => 'technical_fit', 'label' => 'Tech Fit', 'type' => 'rating_1_5', 'weight' => 0.4],
        ['key' => 'communication', 'label' => 'Comm',     'type' => 'rating_1_5', 'weight' => 0.3],
        ['key' => 'english_level', 'label' => 'English',  'type' => 'select',     'weight' => 0, 'options' => ['basic', 'good', 'fluent']],
    ],
    'pass_threshold' => 3.5,
];

test('a passing scorecard flips the application to screened_in and marks passed=true', function () {
    $this->actingAsRecruitmentAdmin();
    $seed = seedStageWithSchema($this, DEFAULT_SCREENING_SCHEMA);
    $job = makeJobOnStage($seed['stage']);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => [
            'technical_fit' => 5,
            'communication' => 4,
            'english_level' => 'fluent',
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.passed', true)
        ->assertJsonPath('data.recommendation', 'advance');

    expect($application->fresh()->status->value)->toBe(CandidateApplicationStatus::ScreenedIn->value);
    expect(CandidateScreening::where('application_id', $application->id)->count())->toBe(1);
});

test('a failing scorecard flips the application to screened_out and marks passed=false', function () {
    $this->actingAsRecruitmentAdmin();
    $seed = seedStageWithSchema($this, DEFAULT_SCREENING_SCHEMA);
    $job = makeJobOnStage($seed['stage']);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => [
            'technical_fit' => 2,
            'communication' => 2,
            'english_level' => 'basic',
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.passed', false)
        ->assertJsonPath('data.recommendation', 'reject');

    expect($application->fresh()->status->value)->toBe(CandidateApplicationStatus::ScreenedOut->value);
});

test('a missing required field is a 422 with a per-key error', function () {
    $this->actingAsRecruitmentAdmin();
    $seed = seedStageWithSchema($this, DEFAULT_SCREENING_SCHEMA);
    $job = makeJobOnStage($seed['stage']);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => ['technical_fit' => 4, 'english_level' => 'good'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scorecard.communication']);
});

test('an out-of-range rating is a 422 with the min/max message', function () {
    $this->actingAsRecruitmentAdmin();
    $seed = seedStageWithSchema($this, DEFAULT_SCREENING_SCHEMA);
    $job = makeJobOnStage($seed['stage']);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => ['technical_fit' => 9, 'communication' => 3, 'english_level' => 'good'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scorecard.technical_fit']);
});

test('an unknown select option is a 422', function () {
    $this->actingAsRecruitmentAdmin();
    $seed = seedStageWithSchema($this, DEFAULT_SCREENING_SCHEMA);
    $job = makeJobOnStage($seed['stage']);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => ['technical_fit' => 4, 'communication' => 4, 'english_level' => 'native'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scorecard.english_level']);
});

test('re-screening overwrites the existing row rather than creating a second one', function () {
    $this->actingAsRecruitmentAdmin();
    $seed = seedStageWithSchema($this, DEFAULT_SCREENING_SCHEMA);
    $job = makeJobOnStage($seed['stage']);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => ['technical_fit' => 5, 'communication' => 5, 'english_level' => 'fluent'],
    ])->assertSuccessful();

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => ['technical_fit' => 2, 'communication' => 2, 'english_level' => 'basic'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.passed', false);

    expect(CandidateScreening::where('application_id', $application->id)->count())->toBe(1);
});

test('screening without a configured schema returns a clear 422', function () {
    $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $stage = $pipeline->stages()->orderBy('display_order')->first();
    // No screening_schema patched — the fallback null trips the guard.
    $job = makeJobOnStage($stage);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => ['anything' => 'ignored'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scorecard']);
});

test('the screening schema endpoint returns the stage schema', function () {
    $this->actingAsRecruitmentAdmin();
    $seed = seedStageWithSchema($this, DEFAULT_SCREENING_SCHEMA);

    $this->getJson("/api/recruitment-pipelines/{$seed['pipeline']->id}/stages/{$seed['stage']->id}/screening-schema")
        ->assertSuccessful()
        ->assertJsonPath('data.pass_threshold', 3.5)
        ->assertJsonCount(3, 'data.fields');
});

test('submitting a screening requires screen-candidates — view-candidates alone is not enough', function () {
    $this->actingAsUserWithPermissions(['view-jobs', 'view-candidates']);
    $seed = seedStageWithSchema($this, DEFAULT_SCREENING_SCHEMA);
    $job = makeJobOnStage($seed['stage']);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    $this->postJson("/api/applications/{$application->id}/screening", [
        'scorecard' => ['technical_fit' => 5, 'communication' => 5, 'english_level' => 'fluent'],
    ])->assertForbidden();
});
