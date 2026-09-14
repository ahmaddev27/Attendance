<?php

use App\Models\Client;
use App\Models\Employee;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\RecruitmentPipeline;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Modules\Recruitment\Services\JobRequirementService;
use App\Shared\Enums\RecruitmentCaseStatus;
use App\Shared\Enums\TaskEntityType;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

function lifecycleTaskLookups(): TaskStatus
{
    TaskStatus::factory()->create(['sort_order' => 1, 'code' => 'todo_'.uniqid()]);
    TaskPriority::factory()->create(['sort_order' => 1, 'code' => 'normal_'.uniqid()]);
    TaskPriority::factory()->create(['sort_order' => 2, 'code' => 'high']);

    return TaskStatus::factory()->doneState()->create(['sort_order' => 9, 'code' => 'done_'.uniqid()]);
}

function lifecycleJob(RecruitmentPipeline $pipeline, User $owner, string $stageCode, array $attributes = []): JobRequirement
{
    $client = Client::create([
        'client_number' => 'C-2026-'.random_int(6000, 6999),
        'company_name' => 'Lifecycle Client '.uniqid(),
        'account_manager_id' => $owner->id,
        'status' => 'active',
    ]);

    $case = RecruitmentCase::create([
        'case_number' => 'RC-2026-'.random_int(6000, 6999),
        'client_id' => $client->id,
        'title' => 'Lifecycle Case '.uniqid(),
        'owner_id' => $owner->id,
        'status' => RecruitmentCaseStatus::Active->value,
    ]);

    return JobRequirement::create([
        'job_number' => 'J-2026-'.random_int(6000, 6999),
        'recruitment_case_id' => $case->id,
        'pipeline_id' => $pipeline->id,
        'current_stage_id' => $pipeline->stages->firstWhere('code', $stageCode)->id,
        'owner_id' => $owner->id,
        'title' => 'Lifecycle Job',
        'openings' => 1,
        'employment_type' => 'full_time',
        'work_mode' => 'remote',
        'status' => 'active',
        'stage_entered_at' => now()->subHour(),
        ...$attributes,
    ]);
}

/**
 * @param  list<string>  $permissions
 */
function recruitmentStaffWithEmployee(array $permissions): User
{
    $user = User::factory()->create(['employee_id' => Employee::factory()->create()->id]);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

function openPipelineTask(JobRequirement $job, Employee $assignee): Task
{
    return Task::query()->create([
        'title' => 'Publish job: '.$job->title,
        'status_id' => TaskStatus::query()->orderBy('sort_order')->value('id'),
        'priority_id' => TaskPriority::query()->orderBy('sort_order')->value('id'),
        'created_by' => null,
        'assigned_to' => $assignee->id,
        'entity_type' => TaskEntityType::JobRequirement->value,
        'entity_id' => $job->id,
    ]);
}

test('a cancelled job cannot be advanced to hired', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $job = lifecycleJob($pipeline, $admin, 'client_decision');

    $this->postJson("/api/jobs/{$job->id}/cancel")->assertOk();

    $this->postJson("/api/jobs/{$job->id}/advance-stage", [
        'target_stage_id' => $pipeline->stages->firstWhere('code', 'hired')->id,
    ])->assertUnprocessable();

    expect($job->fresh()->status->value)->toBe('cancelled');
});

test('cancelling moves the job to the cancelled stage and closes its open pipeline tasks', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    $done = lifecycleTaskLookups();
    $job = lifecycleJob($pipeline, $admin, 'publish');
    $task = openPipelineTask($job, Employee::factory()->create());

    $this->postJson("/api/jobs/{$job->id}/cancel")->assertOk();

    expect($job->fresh()->currentStage->code)->toBe('cancelled')
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($task->fresh()->status_id)->toBe($done->id);
});

test('advancing closes the open task of the stage being left', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    lifecycleTaskLookups();
    $job = lifecycleJob($pipeline, $admin, 'publish', ['publication_url' => 'https://brightgaza.jo/job/9']);
    $task = openPipelineTask($job, Employee::factory()->create());

    $this->postJson("/api/jobs/{$job->id}/advance-stage", [])->assertOk();

    expect($task->fresh()->completed_at)->not->toBeNull();
});

test('only the stage owner, the job owner or a jobs manager can advance a job', function () {
    $this->seedRecruitmentPermissions();
    $pipeline = $this->seedStandardPipeline();
    lifecycleTaskLookups();
    $owner = User::factory()->create();
    $job = lifecycleJob($pipeline, $owner, 'publish');
    $payload = ['fields' => ['publication_url' => 'https://brightgaza.jo/job/10']];

    $this->actingAsUserWithPermissions(['view-jobs', 'advance-job-stage']);
    $this->postJson("/api/jobs/{$job->id}/advance-stage", $payload)->assertForbidden();

    $this->actingAsUserWithPermissions(['view-jobs', 'advance-job-stage', 'publish-jobs']);
    $this->postJson("/api/jobs/{$job->id}/advance-stage", $payload)->assertOk();
});

test('the handoff note becomes the description of the next stage task', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    lifecycleTaskLookups();
    $publisher = recruitmentStaffWithEmployee(['publish-jobs']);
    $job = lifecycleJob($pipeline, $admin, 'new');

    $this->postJson("/api/jobs/{$job->id}/advance-stage", [
        'handoff_note' => 'Use the Arabic description from the client brief.',
    ])->assertOk();

    expect(Task::query()->where('entity_id', $job->id)->where('assigned_to', $publisher->employee_id)->value('description'))
        ->toBe('Use the Arabic description from the client brief.');
});

test('stage tasks go to an active recruitment staff member before a super-admin', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $superAdminWithEmployee = User::factory()->create(['employee_id' => Employee::factory()->create()->id]);
    $superAdminWithEmployee->assignRole('super-admin');
    $pipeline = $this->seedStandardPipeline();
    lifecycleTaskLookups();
    $inactivePublisher = recruitmentStaffWithEmployee(['publish-jobs']);
    $inactivePublisher->forceFill(['is_active' => false])->save();
    $publisher = recruitmentStaffWithEmployee(['publish-jobs']);
    $job = lifecycleJob($pipeline, $admin, 'new');

    $this->postJson("/api/jobs/{$job->id}/advance-stage", [])->assertOk();

    expect(Task::query()->where('entity_id', $job->id)->pluck('assigned_to')->all())->toBe([$publisher->employee_id]);
});

test('a stale copy of the job cannot advance it a second time', function () {
    $admin = $this->actingAsRecruitmentAdmin();
    $pipeline = $this->seedStandardPipeline();
    lifecycleTaskLookups();
    $job = lifecycleJob($pipeline, $admin, 'new');
    $stale = JobRequirement::query()->findOrFail($job->id);
    $stale->load('currentStage');

    $this->postJson("/api/jobs/{$job->id}/advance-stage", [])->assertOk();

    expect(fn () => app(JobRequirementService::class)->advanceStage($stale, [], $admin))
        ->toThrow(ValidationException::class);

    expect($job->fresh()->currentStage->code)->toBe('publish');
});
