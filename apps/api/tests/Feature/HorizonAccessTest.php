<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Horizon authenticates through the session ("web") guard, not Sanctum
// tokens, so these tests act on that guard.

test('guests cannot open the horizon dashboard', function () {
    $this->get('/horizon')->assertForbidden();
});

test('authenticated users without the permission are forbidden', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->get('/horizon')
        ->assertForbidden();
});

test('users holding view-audit-logs can open the dashboard', function () {
    Permission::findOrCreate('view-audit-logs', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('view-audit-logs');

    $this->actingAs($user, 'web')->get('/horizon')->assertOk();
});

test('super-admins can open the dashboard', function () {
    Role::findOrCreate('super-admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user, 'web')->get('/horizon')->assertOk();
});
