<?php

use App\Models\Client;
use App\Models\RecruitmentCase;
use App\Shared\Enums\RecruitmentCaseStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('an admin can create a case under a client', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-6001',
        'company_name' => 'Case Owner Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/recruitment-cases', [
        'client_id' => $client->id,
        'title' => 'Q2 Hiring Wave',
        'owner_id' => $admin->id,
        'target_hires' => 10,
        'priority' => 'high',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Q2 Hiring Wave')
        ->assertJsonPath('data.priority', 'high');

    $this->assertDatabaseHas('recruitment_cases', [
        'client_id' => $client->id,
        'title' => 'Q2 Hiring Wave',
    ]);
});

test('creating a case without manage-recruitment-cases permission is forbidden', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-6002',
        'company_name' => 'RBAC Case Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $viewer = $this->actingAsUserWithPermissions(['view-recruitment-cases']);

    $response = $this->postJson('/api/recruitment-cases', [
        'client_id' => $client->id,
        'title' => 'Should Fail',
        'owner_id' => $viewer->id,
    ]);

    $response->assertForbidden();
});

test('listing cases for a specific client returns only that clients cases', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $clientA = Client::create([
        'client_number' => 'C-2026-6003',
        'company_name' => 'Client A',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $clientB = Client::create([
        'client_number' => 'C-2026-6004',
        'company_name' => 'Client B',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    RecruitmentCase::create([
        'case_number' => 'RC-2026-6001',
        'client_id' => $clientA->id,
        'title' => 'A-1',
        'owner_id' => $admin->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    RecruitmentCase::create([
        'case_number' => 'RC-2026-6002',
        'client_id' => $clientA->id,
        'title' => 'A-2',
        'owner_id' => $admin->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    RecruitmentCase::create([
        'case_number' => 'RC-2026-6003',
        'client_id' => $clientB->id,
        'title' => 'B-1',
        'owner_id' => $admin->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    $response = $this->getJson("/api/clients/{$clientA->id}/cases");

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('an admin can soft-delete a case', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-6005',
        'company_name' => 'Delete Case Client',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-6010',
        'client_id' => $client->id,
        'title' => 'Retired',
        'owner_id' => $admin->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    $this->deleteJson("/api/recruitment-cases/{$case->id}")->assertNoContent();

    $this->assertSoftDeleted('recruitment_cases', ['id' => $case->id]);
});
