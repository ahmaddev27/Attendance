<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\RecruitmentPipeline;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * The hourly scan used to notify for every breached job on every run (a
 * database row each time, and an email when a real mail transport is
 * configured). A breach is now announced once per stage and repeated at
 * most once a day while it stays open.
 */
function slaRepeatOwner(): User
{
    return User::factory()->create(['employee_id' => Employee::factory()->create()->id]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function slaRepeatJob(RecruitmentPipeline $pipeline, User $owner, array $attributes = []): JobRequirement
{
    $client = Client::create([
        'client_number' => 'C-2026-'.random_int(7100, 7999),
        'company_name' => 'SLA Repeat Client '.uniqid(),
        'account_manager_id' => $owner->id,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-'.random_int(7100, 7999),
        'client_id' => $client->id,
        'title' => 'SLA Repeat Case '.uniqid(),
        'owner_id' => $owner->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    return JobRequirement::create([
        'job_number' => 'J-2026-'.random_int(7100, 7999),
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        // Publish carries sla_hours = 24 in the standard pipeline.
        'current_stage_id' => $pipeline->stages->firstWhere('code', 'publish')->id,
        'owner_id' => $owner->id,
        'title' => 'Overdue job',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'status' => 'active',
        'stage_entered_at' => now()->subDays(3),
        ...$attributes,
    ]);
}

test('a breached stage is announced once, not on every hourly scan', function () {
    Notification::fake();
    $this->seedRecruitmentPermissions();
    $pipeline = $this->seedStandardPipeline();
    $owner = slaRepeatOwner();
    slaRepeatJob($pipeline, $owner);

    $this->artisan('recruitment:scan-sla')->assertExitCode(0);
    $this->artisan('recruitment:scan-sla')->assertExitCode(0);

    Notification::assertSentToTimes($owner, TaqatNotification::class, 1);
});

test('a breach still open a day later is announced again', function () {
    Notification::fake();
    $this->seedRecruitmentPermissions();
    $pipeline = $this->seedStandardPipeline();
    $owner = slaRepeatOwner();
    slaRepeatJob($pipeline, $owner);

    $this->artisan('recruitment:scan-sla')->assertExitCode(0);
    $this->travel(25)->hours();
    $this->artisan('recruitment:scan-sla')->assertExitCode(0);

    Notification::assertSentToTimes($owner, TaqatNotification::class, 2);
});

test('a new stage that breaches is announced even within the same day', function () {
    Notification::fake();
    $this->seedRecruitmentPermissions();
    $pipeline = $this->seedStandardPipeline();
    $screening = $pipeline->stages->firstWhere('code', 'screening');
    $screening->update(['sla_hours' => 1]);
    $owner = slaRepeatOwner();
    $job = slaRepeatJob($pipeline, $owner);

    $this->artisan('recruitment:scan-sla')->assertExitCode(0);

    $this->travel(3)->hours();
    $job->update(['current_stage_id' => $screening->id, 'stage_entered_at' => now()->subHours(2)]);

    $this->artisan('recruitment:scan-sla')->assertExitCode(0);

    Notification::assertSentToTimes($owner, TaqatNotification::class, 2);
});

test('jobs on hold are not chased', function () {
    Notification::fake();
    $this->seedRecruitmentPermissions();
    $pipeline = $this->seedStandardPipeline();
    $owner = slaRepeatOwner();
    slaRepeatJob($pipeline, $owner, ['status' => 'on_hold']);

    $this->artisan('recruitment:scan-sla')->assertExitCode(0);

    Notification::assertNothingSent();
});
