<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

beforeEach(function () {
    $this->actingAsSuperAdmin();
});

test('lists companies with department counts', function () {
    $companyA = Company::factory()->create(['name' => 'Alpha Corp']);
    $companyB = Company::factory()->create(['name' => 'Beta Co']);
    Department::factory()->count(2)->create(['company_id' => $companyA->id]);

    $response = $this->getJson('/api/companies');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Alpha Corp')
        ->assertJsonPath('data.0.departments_count', 2)
        ->assertJsonPath('data.1.name', 'Beta Co')
        ->assertJsonPath('data.1.departments_count', 0);
});

test('creates a company', function () {
    $payload = [
        'name' => 'طاقات غزة',
        'timezone' => 'Asia/Gaza',
        'settings' => ['locale' => 'ar'],
    ];

    $response = $this->postJson('/api/companies', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'طاقات غزة')
        ->assertJsonPath('data.timezone', 'Asia/Gaza')
        ->assertJsonPath('data.settings.locale', 'ar');

    $this->assertDatabaseHas('companies', [
        'name' => 'طاقات غزة',
        'timezone' => 'Asia/Gaza',
    ]);
});

test('rejects invalid timezone on create', function () {
    $response = $this->postJson('/api/companies', [
        'name' => 'Rogue',
        'timezone' => 'Mars/Olympus',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('timezone');
});

test('updates a company name and timezone', function () {
    $company = Company::factory()->create(['name' => 'Old', 'timezone' => 'Asia/Amman']);

    $response = $this->patchJson("/api/companies/{$company->id}", [
        'name' => 'New',
        'timezone' => 'Asia/Gaza',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.timezone', 'Asia/Gaza');
});

test('shows a company', function () {
    $company = Company::factory()->create(['name' => 'Acme']);
    Department::factory()->create(['company_id' => $company->id]);

    $response = $this->getJson("/api/companies/{$company->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'Acme')
        ->assertJsonPath('data.departments_count', 1);
});

test('deletes an empty company', function () {
    $company = Company::factory()->create();

    $response = $this->deleteJson("/api/companies/{$company->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('companies', ['id' => $company->id]);
});

test('refuses to delete a company that still has departments', function () {
    $company = Company::factory()->create();
    Department::factory()->create(['company_id' => $company->id]);

    $response = $this->deleteJson("/api/companies/{$company->id}");

    $response->assertUnprocessable()->assertJsonValidationErrors('id');
    $this->assertDatabaseHas('companies', ['id' => $company->id]);
});

test('uploads a logo and exposes the URL on the resource', function () {
    Storage::fake('public');
    $company = Company::factory()->create(['logo_path' => null]);

    $response = $this->postJson("/api/companies/{$company->id}/logo", [
        'logo' => UploadedFile::fake()->image('brand.png', 400, 400),
    ]);

    $response->assertOk();
    $logoUrl = $response->json('data.logo_url');
    expect($logoUrl)->toBeString()->not->toBeEmpty();

    $company->refresh();
    expect($company->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($company->logo_path);
});

test('replaces the previous logo file when a new one is uploaded', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    $this->postJson("/api/companies/{$company->id}/logo", [
        'logo' => UploadedFile::fake()->image('first.png'),
    ])->assertOk();
    $firstPath = $company->fresh()->logo_path;

    $this->postJson("/api/companies/{$company->id}/logo", [
        'logo' => UploadedFile::fake()->image('second.png'),
    ])->assertOk();
    $secondPath = $company->fresh()->logo_path;

    expect($firstPath)->not->toBe($secondPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

test('removes a logo', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    $this->postJson("/api/companies/{$company->id}/logo", [
        'logo' => UploadedFile::fake()->image('brand.png'),
    ])->assertOk();

    $path = $company->fresh()->logo_path;
    expect($path)->not->toBeNull();

    $response = $this->deleteJson("/api/companies/{$company->id}/logo");

    $response->assertOk()->assertJsonPath('data.logo_url', null);
    expect($company->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('rejects a non-image upload', function () {
    Storage::fake('public');
    $company = Company::factory()->create();

    $response = $this->postJson("/api/companies/{$company->id}/logo", [
        'logo' => UploadedFile::fake()->create('malicious.exe', 10, 'application/x-msdownload'),
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('logo');
});

test('returns the full org tree', function () {
    $company = Company::factory()->create(['name' => 'Root Co']);
    $dept = Department::factory()->create(['company_id' => $company->id, 'name' => 'Engineering']);
    $team = Team::factory()->create(['department_id' => $dept->id, 'name' => 'Backend']);
    Employee::factory()->count(3)->create(['team_id' => $team->id, 'department_id' => $dept->id]);

    $response = $this->getJson('/api/companies/tree');

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Root Co')
        ->assertJsonPath('data.0.departments.0.name', 'Engineering')
        ->assertJsonPath('data.0.departments.0.teams.0.name', 'Backend')
        ->assertJsonPath('data.0.departments.0.teams.0.employee_count', 3);
});

test('non-admin without manage-settings cannot access companies endpoints', function () {
    // Fresh non-admin user — spin a bare employee role so the sanctum
    // guard still authenticates, but no permissions apply.
    Permission::findOrCreate('manage-settings', 'web');
    Role::findOrCreate('employee', 'web');
    $user = User::factory()->create();
    $user->assignRole('employee');
    Sanctum::actingAs($user);

    $company = Company::factory()->create();

    $this->getJson('/api/companies')->assertForbidden();
    $this->getJson('/api/companies/tree')->assertForbidden();
    $this->postJson('/api/companies', ['name' => 'x'])->assertForbidden();
    $this->patchJson("/api/companies/{$company->id}", ['name' => 'x'])->assertForbidden();
    $this->deleteJson("/api/companies/{$company->id}")->assertForbidden();
});
