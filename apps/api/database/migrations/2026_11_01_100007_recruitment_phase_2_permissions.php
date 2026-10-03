<?php

declare(strict_types=1);

use Database\Seeders\RecruitmentPermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ships the 5 Phase 2 Recruitment permissions (candidate bank + interview
 * workflow) and extends the `recruiter` + `management` role grants to
 * cover them. Deploys never seed, so production picks the new permissions
 * up via this migration; the seeder itself was updated in the same
 * commit so fresh installs stay in sync.
 *
 * Idempotent + additive: a redeploy that re-runs the seeder (local dev
 * only) is a no-op. Super-admin inherits every permission through the
 * `super-admin` role elsewhere.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const NEW_PERMISSIONS = [
        'view-candidates',
        'manage-candidates',
        'shortlist-candidates',
        'view-interviews',
        'submit-interview-feedback',
    ];

    /**
     * Phase 2 extensions to EXISTING roles — the grants layered onto
     * roles that already shipped in Phase 1. (New roles like
     * `screening_officer` / `interview_coordinator` from the plan are
     * NOT shipped as fixtures; the owner adds them from the admin UI
     * per deployment, same convention as Phase 1 roles.)
     *
     * @var array<string, list<string>>
     */
    private const ROLE_EXTENSIONS = [
        'recruiter' => [
            'view-candidates',
            'manage-candidates',
            'shortlist-candidates',
            'view-interviews',
            'submit-interview-feedback',
        ],
        'management' => [
            'view-candidates',
            'view-interviews',
        ],
    ];

    public function up(): void
    {
        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        $superAdmin = Role::query()->where('name', 'super-admin')->where('guard_name', 'web')->first();
        $superAdmin?->givePermissionTo(self::NEW_PERMISSIONS);

        foreach (self::ROLE_EXTENSIONS as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Revoke before delete so Spatie's role_has_permissions pivot is
        // clean when the permissions themselves are dropped.
        foreach (self::ROLE_EXTENSIONS as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->revokePermissionTo($permissions);
        }

        Permission::query()
            ->whereIn('name', self::NEW_PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
