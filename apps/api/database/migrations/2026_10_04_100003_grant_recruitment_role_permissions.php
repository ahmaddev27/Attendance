<?php

use Database\Seeders\RecruitmentPermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ships the recruitment roles (sales, recruiter, job-publisher) and the
 * read-only recruitment grants for management to databases that already
 * exist; deploys never run seeders. Grants are additive.
 */
return new class extends Migration
{
    /**
     * Roles this migration introduces, as opposed to existing roles it only
     * adds permissions to.
     *
     * @var list<string>
     */
    private const NEW_ROLES = ['sales', 'recruiter', 'job-publisher'];

    public function up(): void
    {
        (new RecruitmentPermissionSeeder)->run();
    }

    public function down(): void
    {
        foreach (RecruitmentPermissionSeeder::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role === null) {
                continue;
            }

            $role->revokePermissionTo($permissions);

            // A role somebody already holds stays, so a rollback never takes
            // a user's role away; an unused new role is removed.
            if (in_array($roleName, self::NEW_ROLES, true) && $role->users()->doesntExist()) {
                $role->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
