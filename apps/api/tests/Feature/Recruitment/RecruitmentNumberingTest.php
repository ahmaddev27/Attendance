<?php

use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\Lead;
use App\Models\RecruitmentCase;
use App\Modules\Recruitment\Services\RecruitmentNumberGenerator;
use App\Shared\Enums\LeadStatus;
use App\Shared\Enums\RecruitmentCaseStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * The *_number columns stay unique across soft-deleted rows, so the next
 * number has to count them too. Otherwise deleting the newest record makes
 * the next create collide on the unique index and fail with a 500.
 */
function numberingCase(int $ownerId, string $caseNumber): RecruitmentCase
{
    $client = Client::create([
        'client_number' => 'C-2026-9'.substr($caseNumber, -3),
        'company_name' => 'Numbering Client '.$caseNumber,
        'account_manager_id' => $ownerId,
        'status' => 'active',
    ]);

    return RecruitmentCase::create([
        'case_number' => $caseNumber,
        'client_id' => $client->id,
        'title' => 'Numbering Case '.$caseNumber,
        'owner_id' => $ownerId,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);
}

test('a deleted lead keeps its number out of reuse', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    foreach (['L-2026-0001', 'L-2026-0002'] as $number) {
        Lead::create([
            'lead_number' => $number,
            'company_name' => "Lead {$number}",
            'source' => 'linkedin',
            'owner_id' => $admin->id,
            'status' => LeadStatus::New->value,
        ]);
    }

    Lead::where('lead_number', 'L-2026-0002')->firstOrFail()->delete();

    expect(app(RecruitmentNumberGenerator::class)->nextLeadNumber(2026))->toBe('L-2026-0003');
});

test('a deleted client keeps its number out of reuse', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    foreach (['C-2026-0001', 'C-2026-0002'] as $number) {
        Client::create([
            'client_number' => $number,
            'company_name' => "Client {$number}",
            'account_manager_id' => $admin->id,
            'status' => 'active',
        ]);
    }

    Client::where('client_number', 'C-2026-0002')->firstOrFail()->delete();

    expect(app(RecruitmentNumberGenerator::class)->nextClientNumber(2026))->toBe('C-2026-0003');
});

test('a deleted recruitment case keeps its number out of reuse', function () {
    $admin = $this->actingAsRecruitmentAdmin();

    numberingCase($admin->id, 'RC-2026-0001');
    numberingCase($admin->id, 'RC-2026-0002')->delete();

    expect(app(RecruitmentNumberGenerator::class)->nextCaseNumber(2026))->toBe('RC-2026-0003');
});

test('deleting the newest job no longer breaks creating the next one', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $this->seedStandardPipeline();
    $case = numberingCase($admin->id, 'RC-2026-0101');

    $payload = [
        'recruitment_case_id' => $case->id,
        'owner_id' => $admin->id,
        'title' => 'Numbered job',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
    ];

    $this->postJson('/api/jobs', $payload)->assertCreated();
    $newest = $this->postJson('/api/jobs', $payload)->assertCreated()->json('data.id');

    $this->deleteJson("/api/jobs/{$newest}")->assertSuccessful();

    $this->postJson('/api/jobs', $payload)->assertCreated();

    expect(JobRequirement::withTrashed()->pluck('job_number')->unique()->count())->toBe(3);
});
