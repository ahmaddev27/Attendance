<?php

use App\Models\Client;
use App\Models\Lead;
use App\Shared\Enums\LeadStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('converting a lead creates a client, case and jobs in one transaction', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();

    $lead = Lead::create([
        'lead_number' => 'L-2026-6001',
        'company_name' => 'Convert Me Co',
        'country' => 'Jordan',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
        'contact_person' => 'Ali',
        'contact_email' => 'ali@convertme.test',
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/convert", [
        'client' => [
            'company_name' => 'Convert Me Co',
            'country' => 'Jordan',
        ],
        'case' => [
            'title' => 'Q1 Remote Hiring',
            'priority' => 'normal',
            'target_hires' => 5,
        ],
        'jobs' => [
            [
                'title' => 'Senior Backend Developer',
                'openings' => 2,
                'employment_type' => 'full_time',
                'work_mode' => 'remote',
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.lead.status', LeadStatus::Converted->value)
        ->assertJsonPath('data.client.company_name', 'Convert Me Co')
        ->assertJsonPath('data.case.title', 'Q1 Remote Hiring');

    expect($response->json('data.jobs'))->toHaveCount(1);

    $this->assertDatabaseHas('clients', ['company_name' => 'Convert Me Co', 'country' => 'Jordan']);
    $this->assertDatabaseHas('recruitment_cases', ['title' => 'Q1 Remote Hiring']);
    $this->assertDatabaseHas('job_requirements', ['title' => 'Senior Backend Developer', 'openings' => 2]);
    $this->assertDatabaseHas('client_contacts', ['full_name' => 'Ali', 'is_primary' => true]);
});

test('a lead already converted cannot be converted again', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();

    $lead = Lead::create([
        'lead_number' => 'L-2026-6002',
        'company_name' => 'Already Converted',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Converted->value,
        'converted_at' => now(),
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/convert", [
        'client' => ['company_name' => 'Already Converted'],
        'case' => ['title' => 'Second try'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

test('a lost lead cannot be converted', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();

    $lead = Lead::create([
        'lead_number' => 'L-2026-6003',
        'company_name' => 'Lost Cause',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Lost->value,
        'lost_at' => now(),
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/convert", [
        'client' => ['company_name' => 'Lost Cause'],
        'case' => ['title' => 'Wont work'],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['status']);
});

test('converting with reuse_client_id links the case to that existing client', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();

    $existing = Client::create([
        'client_number' => 'C-2026-9001',
        'company_name' => 'Existing Client',
        'country' => 'Jordan',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $lead = Lead::create([
        'lead_number' => 'L-2026-6004',
        'company_name' => 'Whatever',
        'country' => 'Jordan',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/convert", [
        'reuse_client_id' => $existing->id,
        'case' => ['title' => 'Reused Client Case'],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.client.id', $existing->id);

    $this->assertDatabaseHas('recruitment_cases', [
        'client_id' => $existing->id,
        'title' => 'Reused Client Case',
    ]);
});

test('converting can create multiple jobs at once', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();

    $lead = Lead::create([
        'lead_number' => 'L-2026-6005',
        'company_name' => 'MultiJob Co',
        'country' => 'UAE',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Qualified->value,
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/convert", [
        'client' => ['company_name' => 'MultiJob Co', 'country' => 'UAE'],
        'case' => ['title' => 'Multi hire'],
        'jobs' => [
            ['title' => 'Backend', 'openings' => 1, 'employment_type' => 'full_time', 'work_mode' => 'remote'],
            ['title' => 'Frontend', 'openings' => 2, 'employment_type' => 'full_time', 'work_mode' => 'hybrid'],
            ['title' => 'DevOps', 'openings' => 1, 'employment_type' => 'contract', 'work_mode' => 'onsite'],
        ],
    ]);

    $response->assertCreated();
    expect($response->json('data.jobs'))->toHaveCount(3);

    $this->assertDatabaseCount('job_requirements', 3);
});

test('a user without convert-leads permission cannot convert a lead', function () {
    $user = $this->actingAsUserWithPermissions(['view-leads', 'manage-leads']);
    $this->seedStandardPipeline();

    $lead = Lead::create([
        'lead_number' => 'L-2026-6006',
        'company_name' => 'Gate Test Co',
        'country' => 'Jordan',
        'source' => 'linkedin',
        'owner_id' => $user->id,
        'status' => LeadStatus::Qualified->value,
    ]);

    $response = $this->postJson("/api/leads/{$lead->id}/convert", [
        'case' => ['title' => 'Forbidden'],
    ]);

    $response->assertForbidden();
});
