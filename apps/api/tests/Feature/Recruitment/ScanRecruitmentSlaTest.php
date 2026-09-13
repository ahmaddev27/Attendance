<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('scan-sla fires a stage-breached notification to the case owner when SLA is exceeded', function () {
    Notification::fake();

    $this->seedRecruitmentPermissions();
    $pipeline = $this->seedStandardPipeline();

    $employee = Employee::factory()->create();
    $owner = User::factory()->create(['employee_id' => $employee->id, 'email' => 'owner@test.test']);

    $client = Client::create([
        'client_number' => 'C-2026-1801',
        'company_name' => 'SLA Client',
        'account_manager_id' => $owner->id,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-1801',
        'client_id' => $client->id,
        'title' => 'SLA Case',
        'owner_id' => $owner->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    // Publish stage carries sla_hours=24 in the standard pipeline.
    $publish = $pipeline->stages->firstWhere('code', 'publish');

    JobRequirement::create([
        'job_number' => 'J-2026-1801',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $publish->id,
        'owner_id' => $owner->id,
        'title' => 'Breached',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now()->subDays(3),
    ]);

    $this->artisan('recruitment:scan-sla')->assertExitCode(0);

    Notification::assertSentTo(
        $owner,
        TaqatNotification::class,
        fn (TaqatNotification $sent) => str_contains($sent->title, 'تجاوز موعد المرحلة'),
    );
});

test('scan-sla does NOT notify when the job is still inside its SLA window', function () {
    Notification::fake();

    $this->seedRecruitmentPermissions();
    $pipeline = $this->seedStandardPipeline();

    $employee = Employee::factory()->create();
    $owner = User::factory()->create(['employee_id' => $employee->id]);

    $client = Client::create([
        'client_number' => 'C-2026-1802',
        'company_name' => 'Within SLA',
        'account_manager_id' => $owner->id,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-1802',
        'client_id' => $client->id,
        'title' => 'Within SLA Case',
        'owner_id' => $owner->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    $publish = $pipeline->stages->firstWhere('code', 'publish');

    JobRequirement::create([
        'job_number' => 'J-2026-1802',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $publish->id,
        'owner_id' => $owner->id,
        'title' => 'Fresh',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now()->subHour(),
    ]);

    $this->artisan('recruitment:scan-sla')->assertExitCode(0);

    Notification::assertNothingSent();
});
