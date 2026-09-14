<?php

use App\Models\Client;
use App\Models\Lead;
use App\Shared\Enums\LeadStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * The plan's dedup policy is "warn, then allow force-create". A hard unique
 * index on (company_name, country) turned the forced create into a 500 and
 * also blocked re-creating a client whose namesake had been deleted.
 */
test('force-creating a client with the same name and country succeeds after the warning', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Client::create([
        'client_number' => 'C-2026-7001',
        'company_name' => 'Twin Co',
        'country' => 'Jordan',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $payload = ['company_name' => 'Twin Co', 'country' => 'Jordan'];

    $this->postJson('/api/clients', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name']);

    $this->postJson('/api/clients', [...$payload, 'force' => true])->assertCreated();

    expect(Client::where('company_name', 'Twin Co')->count())->toBe(2);
});

test('converting a lead cannot reuse a deleted client', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $deleted = Client::create([
        'client_number' => 'C-2026-7003',
        'company_name' => 'Gone Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);
    $deleted->delete();

    $lead = Lead::create([
        'lead_number' => 'L-2026-7003',
        'company_name' => 'Gone Co',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $this->postJson("/api/leads/{$lead->id}/convert", ['reuse_client_id' => $deleted->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reuse_client_id']);
});

test('a client can be created again after its namesake was deleted', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Client::create([
        'client_number' => 'C-2026-7002',
        'company_name' => 'Phoenix Co',
        'country' => 'Egypt',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ])->delete();

    $this->postJson('/api/clients', ['company_name' => 'Phoenix Co', 'country' => 'Egypt'])->assertCreated();
});
