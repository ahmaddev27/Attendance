<?php

declare(strict_types=1);

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\Interview;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

function makeJobForRem($instance): JobRequirement
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

function makeInterviewForRem($instance, array $overrides = []): Interview
{
    $job = makeJobForRem($instance);
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


function fakeNotifier(int $expectedCalls): void
{
    $mock = Mockery::mock(NotificationService::class);
    $mock->shouldReceive('interviewReminder')->times($expectedCalls);
    app()->instance(NotificationService::class, $mock);
}

afterEach(fn () => Carbon::setTestNow());

test('hourly mode picks interviews in the next hour only', function () {
    Carbon::setTestNow('2026-10-10 10:15:00');
    $creator = User::factory()->create();
    makeInterviewForRem($this, ['scheduled_at' => now()->addMinutes(30), 'created_by_user_id' => $creator->id]);
    makeInterviewForRem($this, ['scheduled_at' => now()->addHours(3), 'created_by_user_id' => $creator->id]);
    fakeNotifier(1);

    Artisan::call('recruitment:interview-reminders', ['--horizon' => 'hourly']);
});

test('daily mode picks everything today and nothing tomorrow', function () {
    Carbon::setTestNow('2026-10-10 08:00:00');
    $creator = User::factory()->create();
    makeInterviewForRem($this, ['scheduled_at' => '2026-10-10 15:00:00', 'created_by_user_id' => $creator->id]);
    // Half-open boundary: exactly midnight tomorrow belongs to tomorrow.
    makeInterviewForRem($this, ['scheduled_at' => '2026-10-11 00:00:00', 'created_by_user_id' => $creator->id]);
    fakeNotifier(1);

    Artisan::call('recruitment:interview-reminders', ['--horizon' => 'daily']);
});

test('cancelled interviews are excluded', function () {
    Carbon::setTestNow('2026-10-10 10:15:00');
    $creator = User::factory()->create();
    makeInterviewForRem($this, [
        'scheduled_at' => now()->addMinutes(20),
        'status' => 'cancelled',
        'created_by_user_id' => $creator->id,
    ]);
    fakeNotifier(0);

    Artisan::call('recruitment:interview-reminders', ['--horizon' => 'hourly']);
});
