<?php

use App\Models\Lead;
use App\Shared\Enums\LeadStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('kanban groups active leads by status', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-7001',
        'company_name' => 'New A',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-7002',
        'company_name' => 'New B',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-7003',
        'company_name' => 'Contacted A',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Contacted->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-7004',
        'company_name' => 'Converted Ignored',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Converted->value,
        'converted_at' => now(),
    ]);

    $response = $this->getJson('/api/leads/kanban');

    $response->assertOk();

    $groups = $response->json('data');

    expect($groups[LeadStatus::New->value] ?? [])->toHaveCount(2)
        ->and($groups[LeadStatus::Contacted->value] ?? [])->toHaveCount(1)
        ->and($groups)->not->toHaveKey(LeadStatus::Converted->value);
});

test('kanban orders leads inside a column by next_followup_at with nulls last', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-7010',
        'company_name' => 'No Followup',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
        'next_followup_at' => null,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-7011',
        'company_name' => 'Later Followup',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
        'next_followup_at' => now()->addDays(10),
    ]);

    Lead::create([
        'lead_number' => 'L-2026-7012',
        'company_name' => 'Sooner Followup',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
        'next_followup_at' => now()->addDay(),
    ]);

    $response = $this->getJson('/api/leads/kanban');

    $qualified = $response->json('data.'.LeadStatus::Qualified->value);

    expect($qualified)->toHaveCount(3);
    // Nearest followup first, null last.
    expect($qualified[0]['company_name'])->toBe('Sooner Followup');
    expect($qualified[1]['company_name'])->toBe('Later Followup');
    expect($qualified[2]['company_name'])->toBe('No Followup');
});
