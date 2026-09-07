<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial super-admin account used to bootstrap the system.
     * The role itself is granted separately by RolePermissionSeeder.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['employee_number' => 1000],
            [
                'name' => 'Administrator',
                'email' => 'admin@taqat.local',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );
    }
}
