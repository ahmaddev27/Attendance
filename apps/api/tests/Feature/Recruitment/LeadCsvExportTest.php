<?php

use App\Models\Lead;
use App\Shared\Enums\LeadStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('export returns a CSV with a header row and one row per lead', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-1601',
        'company_name' => 'Export Alpha',
        'source' => 'linkedin',
        'country' => 'Jordan',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-1602',
        'company_name' => 'Export Beta',
        'source' => 'referral',
        'country' => 'UAE',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
    ]);

    $response = $this->get('/api/leads/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $body = $response->streamedContent();

    // BOM prepended for Excel compatibility.
    expect($body)->toStartWith("\xEF\xBB\xBF");

    $lines = array_values(array_filter(explode("\n", trim($body))));
    // 1 header line + 2 data rows = 3.
    expect(count($lines))->toBe(3);

    expect($lines[0])->toContain('company_name');
    expect($body)->toContain('Export Alpha')->toContain('Export Beta');
});

test('a user without export-recruitment-data permission is forbidden from exporting', function () {
    $this->actingAsUserWithPermissions(['view-leads']);

    $this->get('/api/leads/export')->assertForbidden();
});
