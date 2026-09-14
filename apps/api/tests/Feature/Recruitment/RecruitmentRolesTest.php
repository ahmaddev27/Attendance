<?php

use App\Models\User;
use Database\Seeders\RecruitmentPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * Owner decision (2026-09-14): besides super-admin, recruitment is used by
 * sales staff, recruitment officers, a publishing officer and management
 * (read-only). Production never runs seeders, so migration
 * 2026_10_04_100003 ships these grants.
 *
 * @return list<string>
 */
function recruitmentRolePermissions(string $role): array
{
    return Role::findByName($role, 'web')->permissions()->pluck('name')->sort()->values()->all();
}

function actingWithRecruitmentRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    return $user;
}

test('each recruitment role gets exactly the approved permissions', function () {
    foreach (RecruitmentPermissionSeeder::ROLE_PERMISSIONS as $role => $permissions) {
        expect(recruitmentRolePermissions($role))
            ->toContain(...$permissions);
    }

    expect(recruitmentRolePermissions('sales'))->toBe(collect(RecruitmentPermissionSeeder::ROLE_PERMISSIONS['sales'])->sort()->values()->all())
        ->and(recruitmentRolePermissions('recruiter'))->toBe(collect(RecruitmentPermissionSeeder::ROLE_PERMISSIONS['recruiter'])->sort()->values()->all())
        ->and(recruitmentRolePermissions('job-publisher'))->toBe(collect(RecruitmentPermissionSeeder::ROLE_PERMISSIONS['job-publisher'])->sort()->values()->all());
});

test('management can read recruitment but not change it', function () {
    actingWithRecruitmentRole('management');

    $this->getJson('/api/leads')->assertOk();
    $this->getJson('/api/jobs')->assertOk();
    $this->getJson('/api/recruitment/dashboard/kpis')->assertOk();

    $this->postJson('/api/leads', ['company_name' => 'Read Only Co', 'source' => 'linkedin'])->assertForbidden();
});

test('sales staff work leads and clients but do not run the hiring pipeline', function () {
    actingWithRecruitmentRole('sales');

    $this->postJson('/api/leads', ['company_name' => 'Sales Lead Co', 'source' => 'linkedin'])->assertCreated();
    $this->getJson('/api/clients')->assertOk();

    $this->postJson('/api/jobs', [])->assertForbidden();
    $this->postJson('/api/recruitment-pipelines', ['code' => 'x', 'name' => 'X'])->assertForbidden();
});

test('recruitment officers run jobs but do not touch leads', function () {
    actingWithRecruitmentRole('recruiter');

    $this->getJson('/api/jobs')->assertOk();
    $this->postJson('/api/jobs', [])->assertUnprocessable();

    $this->postJson('/api/leads', ['company_name' => 'Not Mine Co', 'source' => 'linkedin'])->assertForbidden();
});

test('the publishing officer sees jobs but cannot create them', function () {
    actingWithRecruitmentRole('job-publisher');

    $this->getJson('/api/jobs')->assertOk();
    $this->postJson('/api/jobs', [])->assertForbidden();
    $this->getJson('/api/leads')->assertForbidden();
});

test('rolling the roles migration back removes the grants and running it again restores them', function () {
    $migration = require database_path('migrations/2026_10_04_100003_grant_recruitment_role_permissions.php');

    $migration->down();

    expect(Role::query()->where('name', 'recruiter')->exists())->toBeFalse()
        ->and(recruitmentRolePermissions('management'))->not->toContain('view-leads');

    $migration->up();

    expect(recruitmentRolePermissions('recruiter'))->toContain('advance-job-stage')
        ->and(recruitmentRolePermissions('management'))->toContain('view-leads');
});
