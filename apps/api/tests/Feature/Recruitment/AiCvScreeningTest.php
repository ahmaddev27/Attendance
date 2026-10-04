<?php

declare(strict_types=1);

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

function aiScreeningApplication($test): CandidateApplication
{
    $stage = $test->seedStandardPipeline()->stages()->orderBy('display_order')->first();
    $owner = User::factory()->create();
    $client = Client::create([
        'client_number' => 'C-'.date('Y').'-'.random_int(1000, 9999),
        'company_name' => 'Hirer', 'country' => 'Palestine', 'city' => 'Gaza', 'owner_id' => $owner->id,
    ]);
    $case = RecruitmentCase::create([
        'case_number' => 'RC-'.date('Y').'-'.random_int(1000, 9999),
        'client_id' => $client->id, 'title' => 'Build', 'owner_id' => $owner->id,
    ]);
    $job = JobRequirement::create([
        'job_number' => 'J-'.date('Y').'-'.random_int(1000, 9999),
        'recruitment_case_id' => $case->id, 'pipeline_id' => $stage->pipeline_id,
        'current_stage_id' => $stage->id, 'owner_id' => $owner->id, 'title' => 'Laravel Dev',
        'employment_type' => 'full_time', 'work_mode' => 'onsite', 'openings' => 1,
        'status' => 'active', 'stage_entered_at' => now(),
        'required_skills' => ['PHP', 'MySQL'], 'required_experience_years' => 3,
    ]);

    return CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);
}

function fakeClaudeText(string $text): void
{
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => $text]]])]);
}

const AI_SCREEN_JSON = '{"overall_score": 82.5, "summary": "Solid fit.", "strengths": ["PHP"], "concerns": ["No MySQL"], "skill_match": {"PHP": true, "MySQL": false}, "recommendation": "advance"}';

beforeEach(fn () => Cache::flush());

test('a valid Claude reply is parsed into the screening structure', function () {
    $this->actingAsRecruitmentAdmin();
    $application = aiScreeningApplication($this);
    fakeClaudeText(AI_SCREEN_JSON);

    $this->postJson("/api/applications/{$application->id}/ai-screen")
        ->assertOk()
        ->assertJsonPath('data.overall_score', 82.5)
        ->assertJsonPath('data.recommendation', 'advance')
        ->assertJsonPath('data.skill_match.MySQL', false)
        ->assertJsonPath('data.strengths.0', 'PHP');

    // PII must never reach the prompt.
    Http::assertSent(fn ($request) => ! str_contains((string) json_encode($request->data()), (string) $application->candidate->email));
});

test('a repeated call is served from cache without hitting Claude again', function () {
    $this->actingAsRecruitmentAdmin();
    $application = aiScreeningApplication($this);
    fakeClaudeText(AI_SCREEN_JSON);

    $this->postJson("/api/applications/{$application->id}/ai-screen")->assertOk();
    $this->postJson("/api/applications/{$application->id}/ai-screen")->assertOk();

    Http::assertSentCount(1);
});

test('non-JSON output from Claude is a 503', function () {
    $this->actingAsRecruitmentAdmin();
    $application = aiScreeningApplication($this);
    fakeClaudeText('Sorry, I cannot do that.');

    $this->postJson("/api/applications/{$application->id}/ai-screen")
        ->assertStatus(503)
        ->assertJsonPath('message', 'AI unavailable, try again later.');
});

test('a network failure is a 503', function () {
    $this->actingAsRecruitmentAdmin();
    $application = aiScreeningApplication($this);
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this->postJson("/api/applications/{$application->id}/ai-screen")->assertStatus(503);
});

test('view-candidates alone cannot run AI screening', function () {
    $this->actingAsUserWithPermissions(['view-candidates']);
    $application = aiScreeningApplication($this);
    Http::fake();

    $this->postJson("/api/applications/{$application->id}/ai-screen")->assertForbidden();
    Http::assertNothingSent();
});
