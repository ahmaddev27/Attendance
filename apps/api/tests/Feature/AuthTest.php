<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('health check returns ok', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJson(['status' => 'ok'])
        ->assertJsonStructure(['status', 'time']);
});

test('login with correct credentials returns a token', function () {
    $user = User::factory()->create([
        'employee_number' => 1234,
        'password' => bcrypt('secret-password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'employee_number' => 1234,
        'password' => 'secret-password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['user' => ['id', 'employee_number', 'name', 'email', 'roles', 'permissions'], 'token'])
        ->assertJsonPath('user.employee_number', $user->employee_number);
});

test('login with wrong password is rejected', function () {
    User::factory()->create([
        'employee_number' => 1234,
        'password' => bcrypt('secret-password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'employee_number' => 1234,
        'password' => 'not-the-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('employee_number');
});

test('login with an inactive user is rejected', function () {
    User::factory()->inactive()->create([
        'employee_number' => 1234,
        'password' => bcrypt('secret-password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'employee_number' => 1234,
        'password' => 'secret-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('employee_number');
});

test('login with an unknown employee number is rejected', function () {
    $response = $this->postJson('/api/auth/login', [
        'employee_number' => 9999,
        'password' => 'whatever',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('employee_number');
});

test('me returns the authenticated user', function () {
    $user = User::factory()->create(['employee_number' => 1234]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

    $response->assertOk()
        ->assertJsonPath('employee_number', $user->employee_number);
});

test('me is rejected without authentication', function () {
    $response = $this->getJson('/api/auth/me');

    $response->assertUnauthorized();
});

test('logout revokes the current access token', function () {
    $user = User::factory()->create(['employee_number' => 1234]);
    $token = $user->createToken('web')->plainTextToken;

    $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/logout');

    $logoutResponse->assertOk()
        ->assertJson(['message' => 'Logged out']);

    // Sanctum's guard caches the resolved user on the guard instance for its
    // lifetime. That instance is reused across requests within a single test
    // (unlike separate real HTTP requests, which each get a fresh guard), so
    // it must be forgotten here to force the token to be re-validated.
    Auth::forgetGuards();

    $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me');

    $meResponse->assertUnauthorized();
});
