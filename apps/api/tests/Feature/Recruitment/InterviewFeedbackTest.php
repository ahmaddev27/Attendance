<?php

declare(strict_types=1);

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\Interview;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Modules\Recruitment\Events\FeedbackSubmitted;
use App\Modules\Recruitment\Services\InterviewFeedbackService;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

function makeJobForFb($instance): JobRequirement
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

function makeInterviewForFb($instance, array $overrides = []): Interview
{
    $job = makeJobForFb($instance);
    $application = CandidateApplication::factory()->for(Candidate::factory())->create([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ]);

    return Interview::create(array_merge([
        'interview_number' => 'INT-'.date('Y').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'application_id' => $application->id,
        'kind' => 'internal',
        'scheduled_at' => now()->addDays(2),
        'location' => 'HQ',
        'created_by_user_id' => User::factory()->create()->id,
    ], $overrides));
}


/**
 * @param  array<string, mixed>  $scorecard
 * @return array<string, mixed>
 */
function feedbackPayload(array $scorecard = ['communication' => 4, 'skills' => 5], string $recommendation = 'hire'): array
{
    return ['scorecard' => $scorecard, 'recommendation' => $recommendation];
}

test('submitting feedback creates a row and fires FeedbackSubmitted', function () {
    Event::fake([FeedbackSubmitted::class]);
    $user = $this->actingAsUserWithPermissions(['submit-interview-feedback']);
    $interview = makeInterviewForFb($this);

    $this->postJson("/api/interviews/{$interview->id}/feedback", feedbackPayload())
        ->assertCreated()
        ->assertJsonPath('data.recommendation', 'hire');

    expect($interview->feedbacks()->where('interviewer_user_id', $user->id)->count())->toBe(1);
    Event::assertDispatched(FeedbackSubmitted::class);
});

test('a second submit from the same interviewer edits the same row', function () {
    Event::fake([FeedbackSubmitted::class]);
    $this->actingAsUserWithPermissions(['submit-interview-feedback']);
    $interview = makeInterviewForFb($this);

    $this->postJson("/api/interviews/{$interview->id}/feedback", feedbackPayload())->assertCreated();
    $this->postJson("/api/interviews/{$interview->id}/feedback", feedbackPayload(['communication' => 2], 'no_hire'))
        ->assertCreated();

    expect($interview->feedbacks()->count())->toBe(1);
    expect($interview->feedbacks()->first()->recommendation->value)->toBe('no_hire');
});

test('PATCH updates the interviewer own feedback and rejects a foreign interview id', function () {
    Event::fake([FeedbackSubmitted::class]);
    $this->actingAsUserWithPermissions(['submit-interview-feedback']);
    $interview = makeInterviewForFb($this);
    $other = makeInterviewForFb($this);

    $id = $this->postJson("/api/interviews/{$interview->id}/feedback", feedbackPayload())->json('data.id');

    $this->patchJson("/api/interviews/{$interview->id}/feedback/{$id}", feedbackPayload(['communication' => 3], 'maybe'))
        ->assertOk()
        ->assertJsonPath('data.recommendation', 'maybe');
    $this->patchJson("/api/interviews/{$other->id}/feedback/{$id}", feedbackPayload())->assertNotFound();
});

test('averageScore is the mean of overall scores across three feedbacks', function () {
    Event::fake([FeedbackSubmitted::class]);
    $this->seedRecruitmentPermissions();
    $interview = makeInterviewForFb($this);
    $service = app(InterviewFeedbackService::class);

    expect($service->averageScore($interview))->toBeNull();

    foreach ([3, 4, 5] as $score) {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('submit-interview-feedback', 'web'));
        $service->submit($interview, $user, feedbackPayload(['overall' => $score]));
    }

    expect($service->averageScore($interview))->toBe(4.0);
});

test('feedback submission requires submit-interview-feedback', function () {
    $interview = makeInterviewForFb($this);
    $this->actingAsUserWithPermissions(['view-interviews']);

    $this->postJson("/api/interviews/{$interview->id}/feedback", feedbackPayload())->assertForbidden();
    expect($interview->feedbacks()->count())->toBe(0);
});
