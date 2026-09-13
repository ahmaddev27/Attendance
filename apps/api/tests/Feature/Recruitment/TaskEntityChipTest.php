<?php

use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\Lead;
use App\Models\RecruitmentCase;
use App\Models\RecruitmentPipeline;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Shared\Enums\LeadStatus;
use App\Shared\Enums\RecruitmentCaseStatus;
use App\Shared\Enums\TaskEntityType;
use Tests\Feature\Concerns\CreatesSuperAdmin;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(CreatesSuperAdmin::class, SeedsRecruitmentPermissions::class);

function makeChipJob(int $ownerId, RecruitmentPipeline $pipeline): JobRequirement
{
    $client = Client::create([
        'client_number' => 'C-2026-'.random_int(7000, 7999),
        'company_name' => 'Chip Client '.uniqid(),
        'account_manager_id' => $ownerId,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-'.random_int(7000, 7999),
        'client_id' => $client->id,
        'title' => 'Chip Case',
        'owner_id' => $ownerId,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    return JobRequirement::create([
        'job_number' => 'J-2026-7101',
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->stages->first()->id,
        'owner_id' => $ownerId,
        'title' => 'Senior Backend Dev',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'stage_entered_at' => now(),
    ]);
}

test('a task linked to a job exposes an entity chip with label and deep link', function () {
    $admin = $this->actingAsSuperAdmin();
    $this->seedRecruitmentPermissions();
    $job = makeChipJob($admin->id, $this->seedStandardPipeline());

    $task = Task::factory()->create([
        'status_id' => TaskStatus::factory(),
        'priority_id' => TaskPriority::factory(),
        'entity_type' => TaskEntityType::JobRequirement,
        'entity_id' => $job->id,
    ]);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.entity.type', 'job_requirement')
        ->assertJsonPath('data.entity.id', $job->id)
        ->assertJsonPath('data.entity.label', 'J-2026-7101 — Senior Backend Dev')
        ->assertJsonPath('data.entity.link', "/recruitment/jobs/{$job->id}");
});

test('task lists resolve entity labels for every linked row', function () {
    $admin = $this->actingAsSuperAdmin();
    $this->seedRecruitmentPermissions();

    $lead = Lead::create([
        'lead_number' => 'L-2026-7102',
        'company_name' => 'Chip Lead Co',
        'source' => 'website',
        'owner_id' => $admin->id,
        'status' => LeadStatus::New->value,
    ]);

    $status = TaskStatus::factory()->create();
    $priority = TaskPriority::factory()->create();

    Task::factory()->create([
        'status_id' => $status->id,
        'priority_id' => $priority->id,
        'entity_type' => TaskEntityType::Lead,
        'entity_id' => $lead->id,
    ]);
    Task::factory()->create([
        'status_id' => $status->id,
        'priority_id' => $priority->id,
    ]);

    $response = $this->getJson('/api/tasks')->assertOk()->assertJsonCount(2, 'data');

    $rows = collect($response->json('data'))->keyBy(fn (array $row) => $row['entity']['type'] ?? 'none');

    expect($rows->get('lead')['entity']['label'])->toBe('L-2026-7102 — Chip Lead Co')
        ->and($rows->get('lead')['entity']['link'])->toBe("/recruitment/leads/{$lead->id}")
        ->and($rows->get('none')['entity'])->toBeNull();
});
