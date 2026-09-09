<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Shared test helper — the super-admin used to exercise admin endpoints.
 *
 * As of the 2026-09-09 security-hardening pass, every admin endpoint is
 * behind a `permission:` middleware (manage-users, manage-workflows,
 * approve-leaves, view-reports, view-audit-logs, ...). Assigning the
 * super-admin role alone isn't enough for spatie/laravel-permission —
 * the role has to actually have those permissions synced. We seed each
 * one via findOrCreate (so a test that runs first doesn't race with one
 * that runs later) and grant them all.
 *
 * Kept in sync with RolePermissionSeeder — if you add a new permission
 * on the app side, add it here too or the tests that exercise its route
 * will start 403'ing.
 */
trait CreatesSuperAdmin
{
    protected function actingAsSuperAdmin(): User
    {
        // Full permission catalog — kept in lock-step with
        // RolePermissionSeeder. When you add a new permission to the
        // seeder, add it here too, or every test route that references
        // it starts throwing PermissionDoesNotExist inside
        // hasPermissionTo/checkPermissionTo.
        // Guard MUST be 'web' — the User model resolves its permission
        // guard to 'web' by default (Sanctum tokens route through it),
        // so the permission has to exist under that specific guard.
        // Without the explicit arg, Spatie writes them under
        // sanctum/api and the runtime check throws "no permission named
        // X for guard web".
        foreach ([
            'manage-users',
            'manage-departments',
            'view-all-attendance',
            'approve-leaves',
            'create-tasks',
            'manage-workflows',
            'view-reports',
            'view-audit-logs',
        ] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $role = Role::findOrCreate('super-admin', 'web');
        $role->syncPermissions(Permission::where('guard_name', 'web')->get());

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Sanctum::actingAs($user);

        return $user;
    }
}
