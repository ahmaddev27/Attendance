<?php

use App\Models\User;
use Database\Seeders\RecruitmentPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

function actingWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * @return list<string>
 */
function grantedPermissions(string $role): array
{
    return Role::findByName($role, 'web')->permissions()->pluck('name')->sort()->values()->all();
}

test('the migration gives every role exactly the approved permissions', function () {
    foreach (RolePermissionSeeder::ROLE_PERMISSIONS as $role => $permissions) {
        // Management also reads recruitment (RecruitmentPermissionSeeder).
        $expected = collect($permissions)
            ->merge(RecruitmentPermissionSeeder::ROLE_PERMISSIONS[$role] ?? [])
            ->unique()
            ->sort()
            ->values()
            ->all();

        expect(grantedPermissions($role))->toBe($expected);
    }

    expect(grantedPermissions('employee'))->toBe([]);
});

test('a department manager reaches leave approvals but not employee management or the audit log', function () {
    actingWithRole('department-manager');

    $this->getJson('/api/leave-requests')->assertOk();
    $this->getJson('/api/employees')->assertForbidden();
    $this->getJson('/api/admin/audit-log')->assertForbidden();
});

test('management reads the audit log but cannot approve leaves', function () {
    actingWithRole('management');

    $this->getJson('/api/admin/audit-log')->assertOk();
    $this->getJson('/api/leave-requests')->assertForbidden();
});

test('a team leader gets nothing beyond creating tasks', function () {
    actingWithRole('team-leader');

    $this->getJson('/api/leave-requests')->assertForbidden();
    $this->getJson('/api/admin/audit-log')->assertForbidden();
});

test('rolling the migration back removes the granted permissions and running it again restores them', function () {
    $migration = require database_path('migrations/2026_10_03_100002_grant_default_role_permissions.php');

    $migration->down();
    expect(grantedPermissions('team-leader'))->toBe([])
        ->and(grantedPermissions('department-manager'))->toBe([]);

    $migration->up();
    expect(grantedPermissions('team-leader'))->toBe(['create-tasks']);
});
