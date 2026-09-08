<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * `php artisan taqat:backfill-users` — creates a login account for every
 * Employee that doesn't have one yet. Idempotent by (employee_id, email,
 * employee_number).
 *
 * Rationale: employees seeded through DemoOrgSeeder (or created by
 * EmployeeService::create() before the auto-user wiring landed) exist as
 * Employee rows without a matching User, so they can't log in.
 * This one-shot backfill closes that gap without touching working
 * accounts — a User already linked to the employee is left as-is.
 *
 * Password behaviour:
 *   • --password=xxx sets the same password for every created user
 *     (useful for QA / demo runs — the default is 'password').
 *   • --random generates a per-user random password and prints them to
 *     the console so the caller can distribute them out-of-band.
 *
 * Role: created users get the `employee` role by default; override with
 * --role=... to seed department managers or team leaders as a batch.
 */
class BackfillEmployeeUsers extends Command
{
    protected $signature = 'taqat:backfill-users
        {--password=password : Password to set for every created user (ignored with --random)}
        {--random : Generate a random 12-char password per user and print it}
        {--role=employee : Spatie role slug to assign (default: employee)}
        {--dry-run : Show what would be done without touching the database}';

    protected $description = 'Create login users for employees that do not have one yet';

    public function handle(): int
    {
        $role = $this->option('role');
        $sharedPassword = (string) $this->option('password');
        $random = (bool) $this->option('random');
        $dryRun = (bool) $this->option('dry-run');

        // Only Spatie roles that actually exist — silently drop the flag
        // for a typoed role name so we don't crash mid-loop.
        $roleExists = Role::query()->where('name', $role)->exists();
        if (! $roleExists) {
            $this->warn("Role '{$role}' does not exist — created users will have no role.");
            $role = null;
        }

        $missing = Employee::query()
            ->whereDoesntHave('user')
            ->orderBy('employee_number')
            ->get();

        if ($missing->isEmpty()) {
            $this->info('All employees already have login accounts. Nothing to do.');
            return self::SUCCESS;
        }

        $this->line("Found {$missing->count()} employees without a login account.");
        if ($dryRun) {
            $this->warn('--dry-run: showing plan only, no writes.');
        }

        $rows = [];
        DB::transaction(function () use ($missing, $sharedPassword, $random, $role, $dryRun, &$rows) {
            foreach ($missing as $emp) {
                $email = $emp->email ?: sprintf('emp%04d@taqat.local', $emp->employee_number);
                $password = $random ? $this->generatePassword() : $sharedPassword;

                if ($dryRun) {
                    $rows[] = [$emp->employee_number, $emp->full_name, $email, $password, 'DRY-RUN'];
                    continue;
                }

                $user = User::query()->create([
                    'employee_number' => $emp->employee_number,
                    'name' => $emp->full_name,
                    'email' => $email,
                    'phone' => $emp->phone,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'employee_id' => $emp->id,
                ]);

                // Back-link on the Employee side too — the schema keeps
                // both employees.user_id and users.employee_id so
                // relationship queries work from either direction, and
                // whereDoesntHave('user') needs the FK on the Employee
                // side to be filled to stop matching the same row on a
                // second run.
                $emp->user_id = $user->id;
                $emp->save();

                if ($role !== null) {
                    $user->assignRole($role);
                }

                $rows[] = [$emp->employee_number, $emp->full_name, $email, $password, 'CREATED'];
            }
        });

        $this->table(
            ['employee_number', 'name', 'email', 'password', 'status'],
            $rows,
        );

        $this->info(sprintf('Done. %d user(s) %s.', count($rows), $dryRun ? 'would be created' : 'created'));

        return self::SUCCESS;
    }

    /**
     * 12-char high-entropy password using the same alphabet as the
     * existing employee-service seeder. Kept away from ambiguous
     * characters (O/0, l/1) so users can read them off an SMS.
     */
    private function generatePassword(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        $len = strlen($alphabet);
        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, $len - 1)];
        }
        return $password;
    }
}
