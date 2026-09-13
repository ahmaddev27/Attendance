<?php

use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\Lead;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Shared\Enums\JobRequirementStatus;
use App\Shared\Enums\LeadStatus;
use App\Shared\Enums\RecruitmentCaseStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('kpis endpoint returns counts that match seeded rows', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();

    // 2 active leads + 1 converted + 1 lost.
    foreach (['A', 'B'] as $i => $name) {
        Lead::create([
            'lead_number' => "L-2026-170{$i}",
            'company_name' => "Active {$name}",
            'source' => 'linkedin',
            'owner_id' => $admin->id,
            'status' => LeadStatus::Qualified->value,
        ]);
    }

    Lead::create([
        'lead_number' => 'L-2026-1710',
        'company_name' => 'Converted',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Converted->value,
        'converted_at' => now(),
    ]);

    Lead::create([
        'lead_number' => 'L-2026-1711',
        'company_name' => 'Lost',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Lost->value,
        'lost_at' => now(),
    ]);

    $client = Client::create([
        'client_number' => 'C-2026-1701',
        'company_name' => 'Active Co',
        'account_manager_id' => $admin->id,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-1701',
        'client_id' => $client->id,
        'title' => 'Open Case',
        'owner_id' => $admin->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    JobRequirement::create([
        'job_number' => 'J-2026-1701',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $admin->id,
        'title' => 'Open Job',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'status' => JobRequirementStatus::Active->value,
        'stage_entered_at' => now(),
    ]);

    $response = $this->getJson('/api/recruitment/dashboard/kpis');

    $response->assertOk()
        ->assertJsonPath('data.leads_active', 2)
        ->assertJsonPath('data.leads_converted', 1)
        ->assertJsonPath('data.leads_lost', 1)
        ->assertJsonPath('data.clients_active', 1)
        ->assertJsonPath('data.cases_open', 1)
        ->assertJsonPath('data.jobs_open', 1);
});

test('funnel endpoint returns leads bucketed by every status', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    Lead::create([
        'lead_number' => 'L-2026-1720',
        'company_name' => 'Funnel New',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    Lead::create([
        'lead_number' => 'L-2026-1721',
        'company_name' => 'Funnel Contacted',
        'source' => 'linkedin',
        'owner_id' => $admin->id,
        'status' => LeadStatus::Contacted->value,
    ]);

    $response = $this->getJson('/api/recruitment/dashboard/funnel');

    $response->assertOk()
        ->assertJsonPath('data.leads_by_status.'.LeadStatus::New->value, 1)
        ->assertJsonPath('data.leads_by_status.'.LeadStatus::Contacted->value, 1)
        ->assertJsonPath('data.leads_by_status.'.LeadStatus::Qualified->value, 0);
});

test('leaderboard returns top converters this quarter', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    $topOwner = User::factory()->create();
    $otherOwner = User::factory()->create();

    // Two conversions this quarter for topOwner.
    Lead::create([
        'lead_number' => 'L-2026-1730',
        'company_name' => 'Top A',
        'source' => 'linkedin',
        'owner_id' => $topOwner->id,
        'status' => LeadStatus::Converted->value,
        'converted_at' => now(),
    ]);

    Lead::create([
        'lead_number' => 'L-2026-1731',
        'company_name' => 'Top B',
        'source' => 'linkedin',
        'owner_id' => $topOwner->id,
        'status' => LeadStatus::Converted->value,
        'converted_at' => now(),
    ]);

    // One conversion for otherOwner.
    Lead::create([
        'lead_number' => 'L-2026-1732',
        'company_name' => 'Other A',
        'source' => 'linkedin',
        'owner_id' => $otherOwner->id,
        'status' => LeadStatus::Converted->value,
        'converted_at' => now(),
    ]);

    $response = $this->getJson('/api/recruitment/dashboard/leaderboard');

    $response->assertOk();

    $rows = $response->json('data');
    expect($rows[0]['owner_id'])->toBe($topOwner->id)
        ->and($rows[0]['conversions'])->toBe(2);
});
