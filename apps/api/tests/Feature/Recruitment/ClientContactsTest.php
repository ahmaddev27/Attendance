<?php

use App\Models\Client;
use App\Models\ClientContact;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('adding multiple contacts to a client persists all of them', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-7001',
        'company_name' => 'Contacts Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $this->postJson("/api/clients/{$client->id}/contacts", [
        'full_name' => 'HR Lead',
        'email' => 'hr@contacts.test',
    ])->assertCreated();

    $this->postJson("/api/clients/{$client->id}/contacts", [
        'full_name' => 'CTO',
        'email' => 'cto@contacts.test',
    ])->assertCreated();

    expect($client->contacts()->count())->toBe(2);
});

test('the first contact on a client becomes primary automatically', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-7002',
        'company_name' => 'First Contact Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $response = $this->postJson("/api/clients/{$client->id}/contacts", [
        'full_name' => 'First Person',
    ]);

    $response->assertCreated();
    expect($client->contacts()->where('is_primary', true)->count())->toBe(1);
});

test('marking a contact primary demotes any existing primary', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-7003',
        'company_name' => 'Primary Swap Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $first = ClientContact::create([
        'client_id' => $client->id,
        'full_name' => 'Old Primary',
        'is_primary' => true,
    ]);

    $second = ClientContact::create([
        'client_id' => $client->id,
        'full_name' => 'New Primary',
        'is_primary' => false,
    ]);

    $this->patchJson("/api/clients/{$client->id}/contacts/{$second->id}", [
        'is_primary' => true,
    ])->assertOk();

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->fresh()->is_primary)->toBeTrue();
});

test('deleting a client cascades to its contacts', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-7004',
        'company_name' => 'Cascade Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    ClientContact::create([
        'client_id' => $client->id,
        'full_name' => 'Contact A',
        'is_primary' => true,
    ]);

    ClientContact::create([
        'client_id' => $client->id,
        'full_name' => 'Contact B',
    ]);

    // Force-delete rather than soft-delete so the cascade actually fires
    // on the DB. Soft-delete on the parent leaves child rows intact.
    $client->contacts()->delete();
    $client->forceDelete();

    $this->assertDatabaseCount('client_contacts', 0);
});

test('an admin can delete a single contact', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $client = Client::create([
        'client_number' => 'C-2026-7005',
        'company_name' => 'Delete Contact Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    ClientContact::create([
        'client_id' => $client->id,
        'full_name' => 'Keeper',
        'is_primary' => true,
    ]);

    $target = ClientContact::create([
        'client_id' => $client->id,
        'full_name' => 'Remove Me',
    ]);

    $this->deleteJson("/api/clients/{$client->id}/contacts/{$target->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('client_contacts', ['id' => $target->id]);
});
