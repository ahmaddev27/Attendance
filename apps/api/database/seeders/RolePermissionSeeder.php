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
        // Rotating third-party credentials (Anthropic, Resend, MTC, WhatsApp)
        // is a strictly higher-blast-radius action than onboarding a user.
        // Gate it behind its own permission so the settings routes never
        // ride on manage-users. Granted ONLY to super-admin below.
        'manage-settings',
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
        // `manage-settings` is implicitly included here because it's in
        // Permission::all() — no lower role should ever receive it.
        Role::findByName('super-admin')
            ->syncPermissions(Permission::all());

        // Bind the super-admin role to a stable identity — the seeded
        // admin email — rather than an employee_number sentinel. Any
        // tenant with 999 employees was previously one create away from
        // having a normal user auto-promoted on the next seeder run.
        // Keep this in sync with AdminUserSeeder::run().
        $admin = User::query()->where('email', 'admin@taqat.local')->first();

        $admin?->assignRole('super-admin');
    }
}
