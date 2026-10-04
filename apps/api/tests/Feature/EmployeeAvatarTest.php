<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Feature\Concerns\CreatesSuperAdmin::class);

beforeEach(function () {
    Storage::fake('public');
    Role::findOrCreate('employee', 'web');
});

test('admin uploads an avatar to the public disk and the resource emits avatar_url', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $response = $this->postJson("/api/employees/{$employee->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
    ]);

    $response->assertCreated()->assertJsonStructure(['data' => ['avatar_url']]);

    $path = $employee->fresh()->avatar_path;
    expect($path)->toStartWith("employee-avatars/{$employee->id}/")->toEndWith('.png');
    Storage::disk('public')->assertExists($path);

    $this->getJson("/api/employees/{$employee->id}")
        ->assertOk()
        ->assertJsonPath('data.avatar_url', Storage::disk('public')->url($path));
});

test('replacing an avatar deletes the previous file', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $this->postJson("/api/employees/{$employee->id}/avatar", ['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $old = $employee->fresh()->avatar_path;

    $this->postJson("/api/employees/{$employee->id}/avatar", ['avatar' => UploadedFile::fake()->image('b.jpg')])
        ->assertCreated();
    $new = $employee->fresh()->avatar_path;

    expect($new)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($new);
});

test('deleting an avatar clears the column and removes the file', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $this->postJson("/api/employees/{$employee->id}/avatar", ['avatar' => UploadedFile::fake()->image('a.webp')]);
    $path = $employee->fresh()->avatar_path;

    $this->deleteJson("/api/employees/{$employee->id}/avatar")->assertNoContent();

    expect($employee->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('an unsupported mime type is rejected with 422', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $this->postJson("/api/employees/{$employee->id}/avatar", [
        'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors('avatar');

    expect($employee->fresh()->avatar_path)->toBeNull();
});

test('an oversize avatar is rejected with 422', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $this->postJson("/api/employees/{$employee->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('big.jpg')->size(2049),
    ])->assertStatus(422)->assertJsonValidationErrors('avatar');
});

test('a user without manage-users cannot upload or delete an avatar', function () {
    $employee = Employee::factory()->create();
    $role = Role::findOrCreate('viewer', 'web');
    $role->givePermissionTo(Permission::findOrCreate('view-reports', 'web'));
    Permission::findOrCreate('manage-users', 'web');

    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    $this->postJson("/api/employees/{$employee->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('a.jpg'),
    ])->assertForbidden();
    $this->deleteJson("/api/employees/{$employee->id}/avatar")->assertForbidden();
});
