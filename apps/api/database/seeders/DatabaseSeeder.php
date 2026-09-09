<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * In production the demo/data seeders (DemoOrgSeeder, AttendanceSeeder,
     * LeaveSeeder, RequestTypesSeeder, TaskSeeder) are refused unless
     * ALLOW_DEMO_SEED=true is set — a stray `db:seed` on prod would
     * otherwise inject demo employees, fake attendance rows, and sample
     * task backlogs into a live tenant.
     */
    public function run(): void
    {
        // Bootstrap-only seeders: their own prod-guards live inside them
        // (AdminUserSeeder refuses without ALLOW_ADMIN_SEED,
        // RolePermissionSeeder is idempotent by design).
        $this->call([
            AdminUserSeeder::class,
            RolePermissionSeeder::class,
        ]);

        if (app()->environment('production') && ! env('ALLOW_DEMO_SEED')) {
            $this->command?->warn(
                'Skipping demo seeders in production. Set ALLOW_DEMO_SEED=true to override.',
            );

            return;
        }

        $this->call([
            DemoOrgSeeder::class,
            AttendanceSeeder::class,
            LeaveSeeder::class,
            RequestTypesSeeder::class,
            TaskSeeder::class,
        ]);
    }
}
