<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * `php artisan taqat:wipe-demo` — nukes every transactional record
 * (attendance, tasks, leaves, requests, notifications, sms/whatsapp
 * logs, push tokens) AND every employee + user EXCEPT the super-admin
 * account(s). Organization tree (departments / teams / positions /
 * leave-types / work-schedules / holidays / task statuses / priorities /
 * tags / workflows / request types) is KEPT — those are configuration,
 * not data, and re-seeding them is expensive.
 *
 * Safety rails:
 *   • Refuses to run without --force in production
 *     (`APP_ENV=production`).
 *   • Requires the operator to type "WIPE" to confirm interactively;
 *     bypass with --confirm.
 *   • Wrapped in a single transaction — the whole thing rolls back on
 *     any failure so a half-wipe never leaves the DB in a broken state.
 *   • Deletes go in FK-safe order (children first, then parents).
 *   • --dry-run just counts and reports; no writes.
 *
 * The super-admin bar: a user is kept if they have the `super-admin`
 * spatie role. `--keep-user-ids=1,2` overrides with an explicit list
 * (useful when the local super-admin uses a different role name).
 */
class WipeDemoData extends Command
{
    protected $signature = 'taqat:wipe-demo
        {--force : Required in production (APP_ENV=production).}
        {--confirm : Skip the interactive "type WIPE to continue" prompt.}
        {--dry-run : Report what would be deleted without touching the DB.}
        {--keep-user-ids= : Comma-separated user ids to keep in addition to super-admins.}';

    protected $description = 'Delete every employee/user/attendance/task/leave/request EXCEPT super-admins. Keeps org config.';

    public function handle(): int
    {
        $isProd = $this->laravel->environment('production');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        // Production safety only applies to WRITE runs. A dry-run just
        // counts rows and is safe to invoke without --force anywhere.
        if ($isProd && ! $force && ! $dryRun) {
            $this->error('APP_ENV is production. Refusing to run without --force.');
            $this->line('Add --force to confirm you really want to wipe production data.');
            $this->line('(--dry-run works without --force — it only reports counts.)');
            return self::FAILURE;
        }

        // Compute the user-id keep-list: every super-admin + any ids
        // passed via --keep-user-ids.
        $keepIds = $this->resolveKeepUserIds();
        $keepUsers = User::query()->whereIn('id', $keepIds)->get();

        if ($keepUsers->isEmpty()) {
            $this->error('No super-admin found and no --keep-user-ids provided.');
            $this->line('This would delete EVERY user including your own login. Aborting.');
            $this->line('Fix: either assign the super-admin role to a user, or pass --keep-user-ids=<yourId>.');
            return self::FAILURE;
        }

        $this->line('Users that will be KEPT:');
        $this->table(['id', 'name', 'email', 'employee_number'], $keepUsers->map(fn (User $u) => [
            $u->id,
            $u->name,
            $u->email,
            $u->employee_number,
        ])->all());

        // Show a summary of what's about to disappear so the operator
        // can back out cleanly.
        $counts = [
            'attendance rows'   => Attendance::query()->count(),
            'tasks'             => Task::query()->count(),
            'leave requests'    => LeaveRequest::query()->count(),
            'leave balances'    => LeaveBalance::query()->count(),
            'requests (workflow)' => RequestModel::query()->count(),
            'employees'         => Employee::withTrashed()->count(),
            'users (non-admin)' => User::query()->whereNotIn('id', $keepIds)->count(),
        ];

        $this->line('');
        $this->line('Records that will be DELETED:');
        $this->table(['what', 'count'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($dryRun) {
            $this->info('--dry-run: no writes.');
            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $answer = $this->ask('Type WIPE (uppercase) to proceed');
            if ($answer !== 'WIPE') {
                $this->line('Cancelled — no changes made.');
                return self::FAILURE;
            }
        }

        // The whole delete runs inside one transaction: if any statement
        // fails (an unforeseen FK, a corrupted row) the DB rolls back and
        // we never leave a half-wiped state that would confuse a retry.
        DB::transaction(function () use ($keepIds) {
            // Cheapest way to satisfy FK order without truncate: run
            // deletes children-first. Table names are lowercased plurals
            // by convention; adjust here if a rename ever lands.
            $tables = [
                // Task graph
                'task_attachments',
                'task_comments',
                'task_history',
                'task_tag_pivot',
                'tasks',
                // Leaves + balances
                'leave_requests',
                'leave_balances',
                // Requests (workflow)
                'request_approvals',
                'requests',
                // Attendance
                'attendances',
                // Notifications (both Laravel's + our SMS/WhatsApp logs)
                'notifications',
                'sms_logs',
                'whatsapp_logs',
                // Push tokens (mobile)
                'push_tokens',
            ];
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            // Employees — hard-delete including trashed so nothing lingers
            // behind a soft-delete flag.
            Employee::withTrashed()->forceDelete();

            // Users — spare the keep-list. Spatie's model_has_roles /
            // model_has_permissions cascade on the user delete via FK.
            User::query()->whereNotIn('id', $keepIds)->delete();
        });

        $this->info('Done. Organization tree (departments, teams, positions, leave types, schedules, holidays, task config) was kept.');
        $this->line('Kept ' . $keepUsers->count() . ' super-admin user(s).');

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function resolveKeepUserIds(): array
    {
        $ids = [];

        // Every super-admin — Spatie exposes role membership via the
        // model_has_roles pivot; we look up the role id then filter
        // users to those linked to it.
        $superAdminRole = Role::query()->where('name', 'super-admin')->first();
        if ($superAdminRole !== null) {
            $ids = DB::table('model_has_roles')
                ->where('role_id', $superAdminRole->id)
                ->where('model_type', User::class)
                ->pluck('model_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        // Extra ids passed via --keep-user-ids (comma-separated).
        $extra = (string) $this->option('keep-user-ids');
        if ($extra !== '') {
            foreach (explode(',', $extra) as $raw) {
                $id = (int) trim($raw);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
