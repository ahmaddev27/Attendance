<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The people recruitment work (leads, cases, jobs) can be handed to. Backs
 * the owner pickers so the UI chooses owners by name instead of a raw id.
 */
class RecruitmentUserRepository
{
    private const RESULT_LIMIT = 50;

    // Gate::before admits this role to every permission check even when no
    // permissions are synced onto it, so the role name alone qualifies.
    private const SUPER_ADMIN_ROLE = 'super-admin';

    /**
     * Mirrors database/seeders/RecruitmentPermissionSeeder: holding any one
     * of these puts a user on the recruitment team.
     *
     * @var list<string>
     */
    private const RECRUITMENT_PERMISSIONS = [
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

    /**
     * Active users with recruitment access, ordered by name. Capped because a
     * picker narrows by search rather than paging through every user.
     *
     * @return Collection<int, User>
     */
    public function assignable(?string $search): Collection
    {
        $query = User::query()
            ->select(['id', 'name', 'employee_number'])
            ->where('is_active', true)
            ->where(function (Builder $holders): void {
                $this->holdsRecruitmentAccess($holders);
            });

        if ($search !== null && $search !== '') {
            $this->applySearch($query, $search);
        }

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::RESULT_LIMIT)
            ->get();
    }

    /**
     * EXISTS subqueries over the permission pivots keep this a single query
     * no matter how many roles or permissions a user holds.
     *
     * @param  Builder<User>  $query
     */
    private function holdsRecruitmentAccess(Builder $query): void
    {
        $query
            ->whereHas('permissions', function (Builder $permissions): void {
                $this->limitToRecruitmentPermissions($permissions);
            })
            ->orWhereHas('roles', function (Builder $roles): void {
                $roles->where(function (Builder $role): void {
                    $role->where($role->qualifyColumn('name'), self::SUPER_ADMIN_ROLE)
                        ->orWhereHas('permissions', function (Builder $permissions): void {
                            $this->limitToRecruitmentPermissions($permissions);
                        });
                });
            });
    }

    private function limitToRecruitmentPermissions(Builder $permissions): void
    {
        $permissions->whereIn($permissions->qualifyColumn('name'), self::RECRUITMENT_PERMISSIONS);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $matches) use ($search): void {
            $like = "%{$search}%";

            $matches->where('name', 'like', $like)
                ->orWhere('email', 'like', $like);

            // Employee numbers match exactly, as in the employee search.
            if (ctype_digit($search)) {
                $matches->orWhere('employee_number', (int) $search);
            }
        });
    }
}
