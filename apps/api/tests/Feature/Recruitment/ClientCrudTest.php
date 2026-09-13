<?php

use App\Models\Client;
use App\Models\RecruitmentCase;
use App\Shared\Enums\RecruitmentCaseStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('an admin can create a client', function () {
    $this->actingAsRecruitmentAdmin();

    $response = $this->postJson('/api/clients', [
        'company_name' => 'Big Co',
        'country' => 'Jordan',
        'industry' => 'Tech',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.company_name', 'Big Co')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('clients', ['company_name' => 'Big Co', 'country' => 'Jordan']);
});

test('creating a client without manage-clients permission is forbidden', function () {
    $this->actingAsUserWithPermissions(['view-clients']);

    $response = $this->postJson('/api/clients', [
        'company_name' => 'No Perm Co',
    ]);

    $response->assertForbidden();
});

test('duplicate company_name + country combos are rejected without force', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Client::create([
        'client_number' => 'C-2026-8001',
        'company_name' => 'Dupe Co',
        'country' => 'Egypt',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/clients', [
        'company_name' => 'Dupe Co',
        'country' => 'Egypt',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['company_name']);
});

test('an admin can update a client', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-8002',
        'company_name' => 'Update Me',
        'country' => 'Jordan',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $response = $this->patchJson("/api/clients/{$client->id}", [
        'industry' => 'FinTech',
    ]);

    $response->assertOk()->assertJsonPath('data.industry', 'FinTech');
});

test('an admin can soft-delete a client', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-8003',
        'company_name' => 'Delete Me',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $this->deleteJson("/api/clients/{$client->id}")->assertNoContent();

    $this->assertSoftDeleted('clients', ['id' => $client->id]);
});

test('client profile endpoint returns nested cases summary', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-8004',
        'company_name' => 'Profile Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    RecruitmentCase::create([
        'case_number' => 'RC-2026-8001',
        'client_id' => $client->id,
        'title' => 'Active case',
        'owner_id' => $admin->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    RecruitmentCase::create([
        'case_number' => 'RC-2026-8002',
        'client_id' => $client->id,
        'title' => 'Completed case',
        'owner_id' => $admin->id,
        'status' => RecruitmentCaseStatus::Completed->value,
        'completed_at' => now(),
    ]);

    $response = $this->getJson("/api/clients/{$client->id}/profile");

    $response->assertOk()
        ->assertJsonPath('data.cases_summary.total', 2)
        ->assertJsonPath('data.cases_summary.open_count', 1);
});

test('listing clients returns paginated rows', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Client::create([
        'client_number' => 'C-2026-8010',
        'company_name' => 'Alpha',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    Client::create([
        'client_number' => 'C-2026-8011',
        'company_name' => 'Beta',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $this->getJson('/api/clients')->assertOk()->assertJsonCount(2, 'data');
});
