<?php

use App\Models\Lead;
use App\Models\User;
use App\Shared\Enums\LeadStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('an admin can create a lead', function () {
    $this->actingAsRecruitmentAdmin();

    $response = $this->postJson('/api/leads', [
        'company_name' => 'ABC Technology',
        'source' => 'linkedin',
        'country' => 'Jordan',
        'contact_person' => 'Ali Hassan',
        'contact_email' => 'ali@abc.test',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.company_name', 'ABC Technology')
        ->assertJsonPath('data.status', LeadStatus::New->value);

    $this->assertDatabaseHas('leads', ['company_name' => 'ABC Technology']);
});

test('creating a lead without the manage-leads permission is forbidden', function () {
    $this->actingAsUserWithPermissions(['view-leads']);

    $response = $this->postJson('/api/leads', [
        'company_name' => 'Beta LLC',
        'source' => 'website',
    ]);

    $response->assertForbidden();
});

test('creating a lead without a source is rejected', function () {
    $this->actingAsRecruitmentAdmin();

    $response = $this->postJson('/api/leads', [
        'company_name' => 'Missing Source Co',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['source']);
});

test('a duplicate company name and country combo is rejected without force', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-9001',
        'company_name' => 'Zain',
        'country' => 'Jordan',
        'source' => 'referral',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->postJson('/api/leads', [
        'company_name' => 'Zain',
        'country' => 'Jordan',
        'source' => 'linkedin',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['company_name']);
});

test('passing force=true bypasses the duplicate warning', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-9002',
        'company_name' => 'Kerten',
        'country' => 'UAE',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->postJson('/api/leads', [
        'company_name' => 'Kerten',
        'country' => 'UAE',
        'source' => 'referral',
        'force' => true,
    ]);

    $response->assertCreated();
});

test('an admin can update a lead', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-9003',
        'company_name' => 'Update Co',
        'source' => 'website',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->patchJson("/api/leads/{$lead->id}", [
        'status' => LeadStatus::Contacted->value,
        'contact_person' => 'Sara',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', LeadStatus::Contacted->value)
        ->assertJsonPath('data.contact_person', 'Sara');
});

test('updates to converted leads are rejected', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-9004',
        'company_name' => 'Frozen Co',
        'source' => 'website',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Converted->value,
        'converted_at' => now(),
    ]);

    $response = $this->patchJson("/api/leads/{$lead->id}", [
        'contact_person' => 'Should Fail',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

test('directly setting status to converted via update is rejected', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-9005',
        'company_name' => 'Direct Convert Co',
        'source' => 'website',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
    ]);

    $response = $this->patchJson("/api/leads/{$lead->id}", [
        'status' => LeadStatus::Converted->value,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

test('an admin can soft-delete a lead', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-9006',
        'company_name' => 'Delete Me',
        'source' => 'website',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->deleteJson("/api/leads/{$lead->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('leads', ['id' => $lead->id]);
});

test('listing leads returns paginated results', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    foreach (['Alpha', 'Beta', 'Gamma'] as $i => $name) {
        Lead::create([
            'lead_number' => "L-2026-800{$i}",
            'company_name' => $name,
            'source' => 'linkedin',
            'owner_id' => $admin->id,
            'status' => LeadStatus::New->value,
        ]);
    }

    $response = $this->getJson('/api/leads');

    $response->assertOk()->assertJsonCount(3, 'data');
});

test('filtering leads by status returns only matching rows', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-8010',
        'company_name' => 'New Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-8011',
        'company_name' => 'Contacted Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Contacted->value,
    ]);

    $response = $this->getJson('/api/leads?status='.LeadStatus::Contacted->value);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.company_name', 'Contacted Co');
});

test('active_only filter excludes converted and lost leads', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-8020',
        'company_name' => 'Active Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-8021',
        'company_name' => 'Lost Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Lost->value,
    ]);

    $response = $this->getJson('/api/leads?active_only=1');

    $response->assertOk()->assertJsonCount(1, 'data');
});

test('search filter matches company name substring', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-8030',
        'company_name' => 'Alpha Tech',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-8031',
        'company_name' => 'Beta Corp',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->getJson('/api/leads?search=Alpha');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.company_name', 'Alpha Tech');
});

test('filtering leads by owner_id returns only that owners rows', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $other = User::factory()->create();

    Lead::create([
        'lead_number' => 'L-2026-8040',
        'company_name' => 'Mine',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-8041',
        'company_name' => 'Theirs',
        'source' => 'linkedin',
        'owner_id' => $other->id,
        'status' => LeadStatus::New->value,
    ]);

    $response = $this->getJson('/api/leads?owner_id='.$other->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.company_name', 'Theirs');
});

test('showing a single lead returns its full payload', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-8050',
        'company_name' => 'Show Me',
        'source' => 'referral',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
        'contact_person' => 'Reader',
    ]);

    $response = $this->getJson("/api/leads/{$lead->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $lead->id)
        ->assertJsonPath('data.contact_person', 'Reader');
});
