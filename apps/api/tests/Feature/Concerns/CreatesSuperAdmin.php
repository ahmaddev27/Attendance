<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * Shared test helper for M2 (Employees + Organization) feature tests.
 * Every endpoint under these modules currently only requires
 * `auth:sanctum` (per-permission gating lands in a later milestone), so
 * authenticating as a super-admin is enough to exercise them.
 */
trait CreatesSuperAdmin
{
    protected function actingAsSuperAdmin(): User
    {
        Role::findOrCreate('super-admin');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Sanctum::actingAs($user);

        return $user;
    }
}
