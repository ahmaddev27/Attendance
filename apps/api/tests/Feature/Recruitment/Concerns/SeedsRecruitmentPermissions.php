<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment\Concerns;

use App\Models\RecruitmentPipeline;
use App\Models\RecruitmentPipelineStage;
use App\Models\User;
use App\Shared\Enums\StageOwnerRule;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Bootstraps the 16 recruitment permissions on the `web` guard so the
 * `permission:` middleware protecting every /api/leads, /api/clients,
 * /api/jobs, /api/recruitment-* endpoint resolves without throwing
 * PermissionDoesNotExist. Also gives every recruitment test a helper
 * that spins up a user with a specific permission subset — the shape
 * the RBAC gate tests exercise directly.
 */
trait SeedsRecruitmentPermissions
{
    /**
     * @var list<string>
     */
    protected static array $recruitmentPermissions = [
        'view-leads',
        'manage-leads',
        'convert-leads',
        'view-clients',
        'manage-clients',
        'view-recruitment-cases',
        'manage-recruitment-cases',
        'view-jobs',
        'manage-jobs',
        'advance-job-stage',
        'publish-jobs',
        'screen-candidates',
        'schedule-interviews',
        'prepare-contracts',
        'manage-recruitment-pipelines',
        'export-recruitment-data',
    ];

    protected function seedRecruitmentPermissions(): void
    {
        foreach (self::$recruitmentPermissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * Authenticate a fresh user granted ONLY the permissions listed —
     * the shape RBAC tests need to prove each gate rejects the wrong
     * grantee and accepts the right one.
     *
     * @param  list<string>  $permissions
     */
    protected function actingAsUserWithPermissions(array $permissions): User
    {
        $this->seedRecruitmentPermissions();

        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Full-access super-admin scoped to the recruitment surface. Uses
     * the `super-admin` role rather than direct grants so behaviour
     * matches the real seeder that assigns permissions to the role.
     */
    protected function actingAsRecruitmentAdmin(): User
    {
        $this->seedRecruitmentPermissions();

        $role = Role::findOrCreate('super-admin', 'web');
        $role->syncPermissions(Permission::where('guard_name', 'web')->get());

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Seeds the default 10-stage "standard" pipeline used by
     * JobRequirementService as the fallback when no pipeline is
     * explicitly supplied. Idempotent — call from any test that opens
     * a job.
     */
    protected function seedStandardPipeline(): RecruitmentPipeline
    {
        $pipeline = RecruitmentPipeline::firstOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standard Job Pipeline',
                'description' => 'Default hiring workflow',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        $stages = [
            ['new',             'New Job',           StageOwnerRule::JobOwner->value,           null,                  null, false, false, []],
            ['publish',         'Publish Job',       StageOwnerRule::Role->value,               'publish-jobs',        24,   true,  false, ['publication_url']],
            ['receiving_apps',  'Receiving Applic.', StageOwnerRule::Role->value,               'screen-candidates',   168,  false, false, []],
            ['screening',       'Screening',         StageOwnerRule::Role->value,               'screen-candidates',   72,   true,  false, []],
            ['shortlist',       'Shortlist',         StageOwnerRule::PreviousStageOwner->value, null,                  24,   true,  false, []],
            ['interviewing',    'Interviewing',      StageOwnerRule::Role->value,               'schedule-interviews', 168,  true,  false, []],
            ['client_decision', 'Client Decision',   StageOwnerRule::JobOwner->value,           null,                  72,   false, false, []],
            ['contracting',     'Contracting',       StageOwnerRule::Role->value,               'prepare-contracts',   48,   true,  false, []],
            ['hired',           'Hired',             StageOwnerRule::None->value,               null,                  null, false, true,  []],
            ['cancelled',       'Cancelled',         StageOwnerRule::None->value,               null,                  null, false, true,  []],
        ];

        foreach ($stages as $order => [$code, $name, $ruleType, $ruleValue, $sla, $autoTask, $terminal, $requires]) {
            RecruitmentPipelineStage::updateOrCreate(
                ['pipeline_id' => $pipeline->id, 'code' => $code],
                [
                    'display_order' => $order + 1,
                    'name' => $name,
                    'owner_rule_type' => $ruleType,
                    'owner_rule_value' => $ruleValue,
                    'sla_hours' => $sla,
                    'auto_generate_task' => $autoTask,
                    'task_title_template' => $code === 'publish' ? 'Publish job: {job.title}' : null,
                    'task_priority' => $code === 'publish' ? 'high' : null,
                    'requires_fields' => $requires,
                    'is_terminal' => $terminal,
                ]
            );
        }

        return $pipeline->fresh() ?? $pipeline;
    }
}
