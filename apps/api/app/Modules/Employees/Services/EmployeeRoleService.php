<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gives an employee's login account its role from the employees page. Until
 * this existed no screen or endpoint could assign a role, so the permission
 * matrix and the recruitment roles were unusable without server access.
 * Each account holds exactly one role.
 */
class EmployeeRoleService
{
    public const SUPER_ADMIN = 'super-admin';

    /**
     * Roles any user with manage-users may hand out. super-admin is only
     * granted by another super-admin.
     *
     * @var list<string>
     */
    public const ASSIGNABLE_ROLES = [
        'management',
        'department-manager',
        'team-leader',
        'employee',
        'sales',
        'recruiter',
        'job-publisher',
    ];

    /**
     * @throws AuthorizationException when a non-super-admin tries to grant super-admin
     * @throws ValidationException when the account cannot take the role
     */
    public function assign(Employee $employee, string $role, User $actor): User
    {
        if ($employee->isSystemAccount()) {
            throw ValidationException::withMessages([
                'role' => 'هذا حساب نظام مرتبط بالمدير العام، ولا يمكن تغيير دوره.',
            ]);
        }

        $user = $this->loginAccountOf($employee);

        if ($user === null) {
            throw ValidationException::withMessages([
                'role' => 'لا يوجد حساب دخول لهذا الموظف بعد. أنشئه أولاً من «إعادة تعيين كلمة السر».',
            ]);
        }

        if ($role === self::SUPER_ADMIN && ! $actor->hasRole(self::SUPER_ADMIN)) {
            throw new AuthorizationException('دور المدير العام لا يمنحه إلا مدير عام.');
        }

        if ($role !== self::SUPER_ADMIN && $user->hasRole(self::SUPER_ADMIN) && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages([
                'role' => 'هذا آخر مدير عام فعّال في النظام، ولا يمكن تغيير دوره.',
            ]);
        }

        DB::transaction(fn () => $user->syncRoles([$role]));

        return $user->load('roles');
    }

    /**
     * Same lookup as EmployeeService::provisionUser: the account linked from
     * users.employee_id, or the one employees.user_id points at.
     */
    private function loginAccountOf(Employee $employee): ?User
    {
        return User::query()->where('employee_id', $employee->id)->first()
            ?? ($employee->user_id ? User::query()->find($employee->user_id) : null);
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        return User::role(self::SUPER_ADMIN)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}
