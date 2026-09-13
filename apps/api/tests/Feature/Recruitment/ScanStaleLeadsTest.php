<?php

use App\Models\Lead;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Shared\Enums\LeadStatus;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

function makeLeadFor(User $owner, string $number, string $status, ?\DateTimeInterface $lastContactAt): Lead
{
    return Lead::create([
        'lead_number' => $number,
        'company_name' => 'Stale Co '.$number,
        'source' => 'website',
        'owner_id' => $owner->id,
        'status' => $status,
        'last_contact_at' => $lastContactAt,
    ]);
}

test('scan-stale-leads notifies the owner of an active lead not contacted inside the window', function () {
    Notification::fake();
    $this->seedRecruitmentPermissions();
    $owner = User::factory()->create();

    makeLeadFor($owner, 'L-2026-7001', LeadStatus::New->value, now()->subDays(10));

    $this->artisan('recruitment:scan-stale-leads')->assertExitCode(0);

    Notification::assertSentTo(
        $owner,
        TaqatNotification::class,
        fn (TaqatNotification $sent) => str_contains($sent->title, 'متابعة عميل محتمل متأخرة')
            && str_contains($sent->body ?? '', '10'),
    );
});

test('scan-stale-leads treats a never-contacted active lead as stale', function () {
    Notification::fake();
    $this->seedRecruitmentPermissions();
    $owner = User::factory()->create();

    makeLeadFor($owner, 'L-2026-7002', LeadStatus::Contacted->value, null);

    $this->artisan('recruitment:scan-stale-leads')->assertExitCode(0);

    Notification::assertSentTo($owner, TaqatNotification::class);
});

test('scan-stale-leads skips recently contacted and terminal leads', function () {
    Notification::fake();
    $this->seedRecruitmentPermissions();
    $owner = User::factory()->create();

    makeLeadFor($owner, 'L-2026-7003', LeadStatus::New->value, now()->subDay());
    makeLeadFor($owner, 'L-2026-7004', LeadStatus::Lost->value, now()->subDays(40));
    makeLeadFor($owner, 'L-2026-7005', LeadStatus::Converted->value, now()->subDays(40));

    $this->artisan('recruitment:scan-stale-leads')->assertExitCode(0);

    Notification::assertNothingSent();
});

test('scan-stale-leads honours a custom --days window', function () {
    Notification::fake();
    $this->seedRecruitmentPermissions();
    $owner = User::factory()->create();

    makeLeadFor($owner, 'L-2026-7006', LeadStatus::New->value, now()->subDays(3));

    $this->artisan('recruitment:scan-stale-leads', ['--days' => 2])->assertExitCode(0);

    Notification::assertSentTo($owner, TaqatNotification::class);
});
