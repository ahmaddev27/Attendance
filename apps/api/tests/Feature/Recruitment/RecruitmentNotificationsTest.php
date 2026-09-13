<?php

use App\Models\Client;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

function makeCaseOwnedBy(User $owner): RecruitmentCase
{
    $client = Client::create([
        'client_number' => 'C-2026-'.random_int(6000, 6999),
        'company_name' => 'Notify Client '.uniqid(),
        'account_manager_id' => $owner->id,
        'status' => 'active',
    ]);

    return RecruitmentCase::create([
        'case_number' => 'RC-2026-'.random_int(6000, 6999),
        'client_id' => $client->id,
        'title' => 'Notify Case '.uniqid(),
        'owner_id' => $owner->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);
}

test('creating a lead for another owner notifies that owner', function () {
    Notification::fake();
    $this->actingAsRecruitmentAdmin();
    $owner = User::factory()->create();

    $this->postJson('/api/leads', [
        'company_name' => 'Handed Over Co',
        'source' => 'website',
        'owner_id' => $owner->id,
    ])->assertCreated();

    Notification::assertSentTo(
        $owner,
        TaqatNotification::class,
        fn (TaqatNotification $sent) => str_contains($sent->title, 'عميل محتمل جديد'),
    );
});

test('creating a lead you own yourself sends no notification', function () {
    Notification::fake();
    $this->actingAsRecruitmentAdmin();

    $this->postJson('/api/leads', [
        'company_name' => 'Self Owned Co',
        'source' => 'website',
    ])->assertCreated();

    Notification::assertNothingSent();
});

test('submitting a job under someone else\'s case notifies the case owner', function () {
    Notification::fake();
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $caseOwner = User::factory()->create();
    $case = makeCaseOwnedBy($caseOwner);

    $this->postJson('/api/jobs', [
        'recruitment_case_id' => $case->id,
        'owner_id' => $admin->id,
        'title' => 'Data Engineer',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
    ])->assertCreated();

    Notification::assertSentTo(
        $caseOwner,
        TaqatNotification::class,
        fn (TaqatNotification $sent) => str_contains($sent->title, 'وظيفة جديدة في حملتك'),
    );
});

test('submitting a job under your own case does not notify you about it', function () {
    Notification::fake();
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $case = makeCaseOwnedBy($admin);

    $this->postJson('/api/jobs', [
        'recruitment_case_id' => $case->id,
        'owner_id' => $admin->id,
        'title' => 'QA Engineer',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'onsite',
    ])->assertCreated();

    Notification::assertNotSentTo(
        $admin,
        TaqatNotification::class,
        fn (TaqatNotification $sent) => str_contains($sent->title, 'وظيفة جديدة في حملتك'),
    );
});
