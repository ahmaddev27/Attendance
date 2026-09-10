<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every super-admin User needs a linked Employee row now that the
 * approval flow (post migration 100007) routes to super-admin as a
 * SpecificRole approver. Without an Employee, the approve/reject/return
 * controller path throws "Your account is not linked to an employee
 * profile" — it stores approver_id in `approvals` which FKs employees.
 *
 * For every super-admin User with a NULL employee_id we:
 *   1. create a minimal "system" Employee row (marked active, hire date
 *      today, no team/department/position — the numbers page filters it
 *      out via a special-marker check the FE can add later if needed);
 *   2. set users.employee_id to that new row.
 *
 * The Employee row uses a distinct employee_number in the 900_000 range
 * so it never collides with the sequential HR numbering starting at 1000.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Resolve the super-admin role id (Spatie's roles table).
        $superAdminRoleId = DB::table('roles')
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $superAdminRoleId) {
            return; // seeder hasn't run yet; nothing to do
        }

        $adminUserIds = DB::table('model_has_roles')
            ->where('role_id', $superAdminRoleId)
            ->where('model_type', 'App\\Models\\User')
            ->pluck('model_id');

        foreach ($adminUserIds as $userId) {
            $user = DB::table('users')->where('id', $userId)->first();

            if (! $user || $user->employee_id) {
                continue;
            }

            // Next available employee_number in the reserved system range.
            $lastSystemNumber = (int) DB::table('employees')
                ->where('employee_number', '>=', 900000)
                ->max('employee_number');

            $newNumber = max($lastSystemNumber + 1, 900000);

            $employeeId = DB::table('employees')->insertGetId([
                'employee_number' => $newNumber,
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'email' => $user->email ?? "system-{$userId}@taqat.local",
                'phone' => null,
                'department_id' => null,
                'team_id' => null,
                'position_id' => null,
                'direct_manager_id' => null,
                'work_schedule_id' => null,
                'status' => 'active',
                'employment_type' => 'full_time',
                'joining_date' => Carbon::now()->toDateString(),
                'user_id' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::table('users')
                ->where('id', $userId)
                ->update(['employee_id' => $employeeId, 'updated_at' => Carbon::now()]);
        }
    }

    public function down(): void
    {
        // Non-reversible on purpose: rolling back would orphan the
        // approvals/attendance rows that may have been recorded against
        // these employee ids in the interim.
    }
};
