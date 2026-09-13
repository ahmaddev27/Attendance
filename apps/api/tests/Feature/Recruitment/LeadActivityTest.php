<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Shared\Enums\LeadStatus;
use Illuminate\Support\Carbon;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('a user can log a call activity on a lead', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-5001',
        'company_name' => 'Activity Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/activities", [
        'type' => 'call',
        'subject' => 'Intro call',
        'body' => 'Discussed hiring needs',
        'occurred_at' => now()->toIso8601String(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'call')
        ->assertJsonPath('data.subject', 'Intro call');

    $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'call']);
});

test('a user can log a meeting activity', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-5002',
        'company_name' => 'Meeting Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/activities", [
        'type' => 'meeting',
        'subject' => 'Kickoff',
        'occurred_at' => now()->toIso8601String(),
    ]);

    $response->assertCreated()->assertJsonPath('data.type', 'meeting');
});

test('a user can log a note activity', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-5003',
        'company_name' => 'Note Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/activities", [
        'type' => 'note',
        'subject' => 'Follow up next week',
        'occurred_at' => now()->toIso8601String(),
    ]);

    $response->assertCreated()->assertJsonPath('data.type', 'note');
});

test('logging a call bumps the leads last_contact_at', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-5004',
        'company_name' => 'Contact Bump Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
        'last_contact_at' => null,
    ]);

    $when = Carbon::now()->subHour();

    $this->postJson("/api/leads/{$lead->id}/activities", [
        'type' => 'call',
        'occurred_at' => $when->toIso8601String(),
    ])->assertCreated();

    $lead->refresh();
    expect($lead->last_contact_at)->not->toBeNull();
});

test('creating a lead automatically emits a system created activity', function () {
    $this->actingAsRecruitmentAdmin();

    $this->postJson('/api/leads', [
        'company_name' => 'Auto Log Co',
        'source' => 'linkedin',
    ])->assertCreated();

    $lead = Lead::where('company_name', 'Auto Log Co')->firstOrFail();
    expect($lead->activities()->where('type', 'note')->count())->toBeGreaterThanOrEqual(1);
});

test('a status change through update auto-logs a status_change activity', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-5006',
        'company_name' => 'Status Change Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $this->patchJson("/api/leads/{$lead->id}", [
        'status' => LeadStatus::Contacted->value,
    ])->assertOk();

    $this->assertDatabaseHas('lead_activities', [
        'lead_id' => $lead->id,
        'type' => 'status_change',
    ]);
});

test('lead activities index returns entries newest-first', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-5007',
        'company_name' => 'Timeline Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    LeadActivity::create([
        'lead_id' => $lead->id,
        'user_id' => $admin->id,
        'type' => 'note',
        'subject' => 'Older',
        'occurred_at' => now()->subDays(2),
    ]);

    LeadActivity::create([
        'lead_id' => $lead->id,
        'user_id' => $admin->id,
        'type' => 'note',
        'subject' => 'Newer',
        'occurred_at' => now(),
    ]);

    $response = $this->getJson("/api/leads/{$lead->id}/activities");

    $response->assertOk();
    $items = $response->json('data');
    expect($items[0]['subject'])->toBe('Newer');
});
