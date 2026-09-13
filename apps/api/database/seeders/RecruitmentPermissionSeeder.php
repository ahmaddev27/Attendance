<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the 16 Recruitment-module permissions and grants them to the
 * super-admin role. Everything else — sales-rep, recruiter,
 * account-manager sample roles — is expected to be configured from the
 * admin UI per tenant rather than shipped as fixtures (see
 * docs/recruitment/03-phase-1-plan.md#4).
 *
 * Idempotent: rerun is a no-op after the first successful run.
 */
class RecruitmentPermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
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

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        $superAdmin = Role::query()->where('name', 'super-admin')->first();

        // RolePermissionSeeder is expected to run before this one (it's
        // listed first in DatabaseSeeder), but guard against the empty
        // case anyway — a fresh migration run + this seeder in
        // isolation shouldn't crash.
        $superAdmin?->givePermissionTo(self::PERMISSIONS);
    }
}
