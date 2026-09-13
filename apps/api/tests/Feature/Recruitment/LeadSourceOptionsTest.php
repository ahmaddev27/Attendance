<?php

use App\Models\Lead;
use App\Models\Setting;
use App\Shared\Enums\LeadStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * @param  list<array{value: string, label: string}>  $items
 */
function configureLeadSources(array $items): void
{
    Setting::updateOrCreate(
        ['key' => 'recruitment.lead_sources'],
        ['value' => json_encode($items, JSON_UNESCAPED_UNICODE), 'encrypted' => false, 'group' => 'recruitment'],
    );
}

test('a lead can use a source an admin added to the list', function () {
    $this->actingAsRecruitmentAdmin();
    configureLeadSources([['value' => 'tiktok', 'label' => 'تيك توك']]);

    $this->postJson('/api/leads', [
        'company_name' => 'Short Video Co',
        'source' => 'tiktok',
    ])->assertCreated()->assertJsonPath('data.source', 'tiktok');
});

test('a source outside the configured list is rejected', function () {
    $this->actingAsRecruitmentAdmin();
    configureLeadSources([['value' => 'tiktok', 'label' => 'تيك توك']]);

    $this->postJson('/api/leads', [
        'company_name' => 'Old Habit Co',
        'source' => 'linkedin',
    ])->assertUnprocessable()->assertJsonValidationErrors(['source']);
});

test('a lead keeps a source that was removed from the list after it was saved', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $lead = Lead::create([
        'lead_number' => 'L-2026-8101',
        'company_name' => 'Legacy Source Co',
        'source' => 'event',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    configureLeadSources([['value' => 'tiktok', 'label' => 'تيك توك']]);

    $this->patchJson("/api/leads/{$lead->id}", [
        'company_name' => 'Legacy Source Co (renamed)',
        'source' => 'event',
    ])->assertOk()->assertJsonPath('data.source', 'event');

    $this->patchJson("/api/leads/{$lead->id}", ['source' => 'partner'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);
});
