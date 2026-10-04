<?php

declare(strict_types=1);

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\Interview;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Modules\Recruitment\Events\InterviewCompleted;
use App\Modules\Recruitment\Events\InterviewScheduled;
use App\Modules\Recruitment\Services\InterviewFeedbackService;
use App\Shared\Enums\InterviewStatus;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

function makeJobForSched($instance): JobRequirement
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

function makeInterviewForSched($instance, array $overrides = []): Interview
{
    $job = makeJobForSched($instance);
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
 * @return array<string, mixed>
 */
function schedulePayload(array $overrides = []): array
{
    return array_merge([
        'kind' => 'internal',
        'scheduled_at' => now()->addDays(3)->toIso8601String(),
        'duration_minutes' => 45,
        'location' => 'HQ Room 2',
    ], $overrides);
}

function makeApplicationForSched($instance, array $state = []): CandidateApplication
{
    $job = makeJobForSched($instance);

    return CandidateApplication::factory()->for(Candidate::factory())->create(array_merge([
        'job_requirement_id' => $job->id,
        'current_stage_id' => $job->current_stage_id,
    ], $state));
}

test('scheduling creates a numbered interview, mirrors interviewing status and fires the event', function () {
    Event::fake([InterviewScheduled::class]);
    $this->actingAsRecruitmentAdmin();
    $application = makeApplicationForSched($this);

    $response = $this->postJson("/api/applications/{$application->id}/interviews", schedulePayload())
        ->assertCreated();

    expect($response->json('data.interview_number'))->toMatch('/^INT-\d{4}-\d{5}$/');
    expect(Interview::where('application_id', $application->id)->count())->toBe(1);
    expect($application->fresh()->status->value)->toBe('interviewing');
    Event::assertDispatched(InterviewScheduled::class);
});

test('scheduling is rejected with 422 on a terminal application', function (string $status) {
    $this->actingAsRecruitmentAdmin();
    $application = makeApplicationForSched($this, ['status' => $status]);

    $this->postJson("/api/applications/{$application->id}/interviews", schedulePayload())
        ->assertStatus(422);

    expect(Interview::count())->toBe(0);
})->with(['rejected', 'withdrawn', 'hired']);

test('cancel flips status and stores the reason; a second cancel is a no-op', function () {
    $this->actingAsRecruitmentAdmin();
    $interview = makeInterviewForSched($this);

    $this->postJson("/api/interviews/{$interview->id}/cancel", ['reason' => 'Candidate unavailable'])
        ->assertOk();
    $this->postJson("/api/interviews/{$interview->id}/cancel", ['reason' => 'Overwrite attempt'])
        ->assertOk();

    $fresh = $interview->fresh();
    expect($fresh->status)->toBe(InterviewStatus::Cancelled);
    expect($fresh->cancelled_reason)->toBe('Candidate unavailable');
});

test('reschedule creates a linked replacement and flips the old row to rescheduled', function () {
    Event::fake([InterviewScheduled::class]);
    $this->actingAsRecruitmentAdmin();
    $old = makeInterviewForSched($this);

    $response = $this->postJson("/api/interviews/{$old->id}/reschedule", [
        'scheduled_at' => now()->addDays(5)->toIso8601String(),
        'duration_minutes' => 30,
    ])->assertCreated();

    $newId = $response->json('data.id');
    expect($newId)->not->toBe($old->id);
    expect(Interview::find($newId)->rescheduled_from_id)->toBe($old->id);
    expect($old->fresh()->status)->toBe(InterviewStatus::Rescheduled);
    Event::assertDispatched(InterviewScheduled::class);
});

test('complete flips to completed and fires InterviewCompleted only when feedback exists', function () {
    Event::fake([InterviewCompleted::class]);
    $admin = $this->actingAsRecruitmentAdmin();

    $bare = makeInterviewForSched($this);
    $this->postJson("/api/interviews/{$bare->id}/complete")->assertOk();
    expect($bare->fresh()->status)->toBe(InterviewStatus::Completed);
    Event::assertNotDispatched(InterviewCompleted::class);

    $withFeedback = makeInterviewForSched($this);
    app(InterviewFeedbackService::class)->submit($withFeedback, $admin, [
        'scorecard' => ['communication' => 4],
        'recommendation' => 'hire',
    ]);
    $this->postJson("/api/interviews/{$withFeedback->id}/complete")->assertOk();
    Event::assertDispatched(InterviewCompleted::class, 1);
});

test('writes require schedule-interviews', function () {
    $interview = makeInterviewForSched($this);
    $application = $interview->application;
    $this->actingAsUserWithPermissions(['view-interviews']);

    $this->postJson("/api/applications/{$application->id}/interviews", schedulePayload())->assertForbidden();
    $this->patchJson("/api/interviews/{$interview->id}", ['location' => 'X'])->assertForbidden();
    $this->postJson("/api/interviews/{$interview->id}/cancel")->assertForbidden();
    $this->postJson("/api/interviews/{$interview->id}/reschedule", ['scheduled_at' => now()->addDay()->toIso8601String()])->assertForbidden();
    $this->postJson("/api/interviews/{$interview->id}/complete")->assertForbidden();
});

test('reads require view-interviews and writers can update', function () {
    $interview = makeInterviewForSched($this);
    $this->actingAsUserWithPermissions(['schedule-interviews']);

    $this->getJson('/api/interviews')->assertForbidden();
    $this->getJson("/api/interviews/{$interview->id}")->assertForbidden();
    $this->patchJson("/api/interviews/{$interview->id}", ['location' => 'Room 9'])->assertOk();

    $this->actingAsUserWithPermissions(['view-interviews']);
    $this->getJson('/api/interviews')->assertOk();
    $this->getJson("/api/interviews/{$interview->id}")->assertOk();
});
