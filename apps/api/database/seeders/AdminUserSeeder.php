<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial super-admin account used to bootstrap the system.
     * The role itself is granted separately by RolePermissionSeeder.
     *
     * Behaviour rules — enforced so a re-seed can't hijack a live tenant:
     *   - Refuses to run in `production` unless ALLOW_ADMIN_SEED=true is
     *     set explicitly, since a re-run would otherwise rewrite the
     *     super-admin password.
     *   - Uses firstOrCreate so an existing admin row is NEVER updated.
     *     Only the initial insert sets a password, and that password is a
     *     32-char random string echoed once to stdout — no hard-coded
     *     default that could reach a public deployment.
     */
    public function run(): void
    {
        if (app()->environment('production') && ! env('ALLOW_ADMIN_SEED')) {
            $this->command?->warn(
                'Skipping AdminUserSeeder in production. Set ALLOW_ADMIN_SEED=true to override.',
            );

            return;
        }

        $existing = User::query()->where('employee_number', 1000)->first();

        if ($existing !== null) {
            $this->command?->info('AdminUserSeeder: admin (employee_number=1000) already exists — leaving password unchanged.');

            return;
        }

        // 32-char password including symbols/digits/mixed case.
        $plaintextPassword = Str::password(32);

        User::query()->create([
            'employee_number' => 1000,
            'name' => 'Administrator',
            'email' => 'admin@taqat.local',
            'password' => Hash::make($plaintextPassword),
            'is_active' => true,
        ]);

        $this->command?->warn('AdminUserSeeder: created admin@taqat.local with a one-time password.');
        $this->command?->warn('  Save it now — it will not be shown again:');
        $this->command?->line('  '.$plaintextPassword);
    }
}
