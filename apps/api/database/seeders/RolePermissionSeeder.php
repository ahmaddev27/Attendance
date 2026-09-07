<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * The five RBAC roles defined for TAQAT (see docs/v2/01-architecture.md).
     *
     * @var list<string>
     */
    private const ROLES = [
        'super-admin',
        'management',
        'department-manager',
        'team-leader',
        'employee',
    ];

    /**
     * Baseline permissions covering the Phase 1 modules.
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'manage-users',
        'manage-departments',
        'view-all-attendance',
        'approve-leaves',
        'create-tasks',
        'manage-workflows',
        'view-reports',
        'view-audit-logs',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role);
        }

        // The super-admin role is granted every permission; other roles are
        // refined per-module as their features are built in later milestones.
        Role::findByName('super-admin')
            ->syncPermissions(Permission::all());

        $admin = User::query()->where('employee_number', 1000)->first();

        $admin?->assignRole('super-admin');
    }
}
