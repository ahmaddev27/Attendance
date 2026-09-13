<?php

use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\Lead;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Shared\Enums\LeadStatus;
use App\Shared\Enums\RecruitmentCaseStatus;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

test('GET /api/leads without view-leads returns 403', function () {
    $this->actingAsUserWithPermissions([]);

    $this->getJson('/api/leads')->assertForbidden();
});

test('GET /api/leads with view-leads returns 200', function () {
    $this->actingAsUserWithPermissions(['view-leads']);

    $this->getJson('/api/leads')->assertOk();
});

test('POST /api/leads without manage-leads returns 403', function () {
    $this->actingAsUserWithPermissions(['view-leads']);

    $this->postJson('/api/leads', [
        'company_name' => 'X',
        'source' => 'linkedin',
    ])->assertForbidden();
});

test('GET /api/clients without view-clients returns 403', function () {
    $this->actingAsUserWithPermissions([]);

    $this->getJson('/api/clients')->assertForbidden();
});

test('POST /api/clients without manage-clients returns 403', function () {
    $this->actingAsUserWithPermissions(['view-clients']);

    $this->postJson('/api/clients', [
        'company_name' => 'RBAC Client',
    ])->assertForbidden();
});

test('GET /api/jobs without view-jobs returns 403', function () {
    $this->actingAsUserWithPermissions([]);

    $this->getJson('/api/jobs')->assertForbidden();
});

test('POST /api/jobs without manage-jobs returns 403', function () {
    $this->actingAsUserWithPermissions(['view-jobs']);

    $this->postJson('/api/jobs', [
        'recruitment_case_id' => 1,
        'owner_id' => 1,
        'title' => 'x',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
    ])->assertForbidden();
});

test('GET /api/recruitment-cases without view-recruitment-cases returns 403', function () {
    $this->actingAsUserWithPermissions([]);

    $this->getJson('/api/recruitment-cases')->assertForbidden();
});

test('POST /api/recruitment-cases without manage-recruitment-cases returns 403', function () {
    $this->actingAsUserWithPermissions(['view-recruitment-cases']);

    $this->postJson('/api/recruitment-cases', [
        'client_id' => 1,
        'title' => 'x',
        'owner_id' => 1,
    ])->assertForbidden();
});

test('POST convert without convert-leads returns 403', function () {
    $user = $this->actingAsUserWithPermissions(['view-leads', 'manage-leads']);
    $this->seedStandardPipeline();

    $lead = Lead::create([
        'lead_number' => 'L-2026-1901',
        'company_name' => 'RBAC Lead',
        'source' => 'linkedin',
        'owner_id' => $user->id,
        'status' => LeadStatus::Qualified->value,
    ]);

    $this->postJson("/api/leads/{$lead->id}/convert", [
        'client' => ['company_name' => 'RBAC Lead'],
        'case' => ['title' => 'x'],
    ])->assertForbidden();
});

test('POST advance-stage without advance-job-stage returns 403', function () {
    $user = $this->actingAsUserWithPermissions(['view-jobs', 'manage-jobs']);
    $pipeline = $this->seedStandardPipeline();

    $client = Client::create([
        'client_number' => 'C-2026-1901',
        'company_name' => 'RBAC Advance',
        'account_manager_id' => $user->id,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-1901',
        'client_id' => $client->id,
        'title' => 'RBAC Advance Case',
        'owner_id' => $user->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    $job = JobRequirement::create([
        'job_number' => 'J-2026-1901',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->firstStage()->id,
        'owner_id' => $user->id,
        'title' => 'RBAC Advance Job',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);

    $this->postJson("/api/jobs/{$job->id}/advance-stage", [])->assertForbidden();
});

test('GET /api/recruitment-pipelines without manage-recruitment-pipelines returns 403', function () {
    $this->actingAsUserWithPermissions(['view-jobs']);

    $this->getJson('/api/recruitment-pipelines')->assertForbidden();
});

test('GET /api/leads/export without export-recruitment-data returns 403', function () {
    $this->actingAsUserWithPermissions(['view-leads']);

    $this->getJson('/api/leads/export')->assertForbidden();
});

test('GET /api/recruitment/dashboard/kpis without view-leads or view-jobs returns 403', function () {
    $this->actingAsUserWithPermissions(['view-clients']);

    $this->getJson('/api/recruitment/dashboard/kpis')->assertForbidden();
});
