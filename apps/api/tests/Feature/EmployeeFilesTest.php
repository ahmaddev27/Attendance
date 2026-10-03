<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Modules\Employees\Services\EmployeeFileService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(\Tests\Feature\Concerns\CreatesSuperAdmin::class);

/**
 * Covers the national-ID + employment-contract upload endpoints:
 *   - upload stores under employee-files/{id}/{kind}/{uuid}.{ext}
 *   - signed download works for admin and for the employee themselves
 *   - delete removes the file and clears the column
 *   - unique national_id is enforced
 *   - birth_date on existing employees still round-trips (regression guard)
 */
beforeEach(function () {
    // Private disk — the files live under storage/app and are only reachable
    // through signed download routes. Fake it so every test starts clean.
    Storage::fake(EmployeeFileService::DISK);

    // EmployeeService::create() provisions a login user and assigns the
    // 'employee' role. RefreshDatabase wipes the roles table every test,
    // so re-create the role here or any create flow throws.
    Role::findOrCreate('employee', 'web');
});

test('admin uploads a national ID image and the path shape matches the scheme', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $file = UploadedFile::fake()->image('id-card.jpg');

    $response = $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => $file],
    );

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['path']]);

    $path = $response->json('data.path');

    expect($path)
        ->toStartWith("employee-files/{$employee->id}/national-id/")
        ->toEndWith('.jpg');

    Storage::disk(EmployeeFileService::DISK)->assertExists($path);
    expect($employee->fresh()->national_id_image_path)->toBe($path);
});

test('admin uploads an employment contract PDF', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $file = UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf');

    $response = $this->postJson(
        "/api/employees/{$employee->id}/employment-contract",
        ['file' => $file],
    );

    $response->assertCreated();
    $path = $response->json('data.path');
    expect($path)
        ->toStartWith("employee-files/{$employee->id}/contract/")
        ->toEndWith('.pdf');
    Storage::disk(EmployeeFileService::DISK)->assertExists($path);
});

test('non-admin cannot upload national ID image', function () {
    $employee = Employee::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($other); // no manage-users permission

    $response = $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => UploadedFile::fake()->image('id.png')],
    );

    $response->assertForbidden();
});

test('national ID upload rejects oversized files', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $tooLarge = UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf');

    $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => $tooLarge],
    )->assertUnprocessable();
});

test('national ID upload rejects disallowed mime types', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $exe = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

    $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => $exe],
    )->assertUnprocessable();
});

test('replacing a national ID image removes the previous file', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $first = $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => UploadedFile::fake()->image('old.jpg')],
    )->json('data.path');

    $second = $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => UploadedFile::fake()->image('new.jpg')],
    )->json('data.path');

    expect($second)->not->toBe($first);
    Storage::disk(EmployeeFileService::DISK)->assertMissing($first);
    Storage::disk(EmployeeFileService::DISK)->assertExists($second);
});

test('admin downloads the national ID image through a signed URL', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => UploadedFile::fake()->image('id.jpg')],
    )->assertCreated();

    $signed = URL::temporarySignedRoute(
        'employees.national-id.download',
        now()->addMinutes(5),
        ['employee' => $employee->id],
    );

    $this->get($signed)->assertOk();
});

test('the employee themselves can download their own national ID image', function () {
    // Admin uploads the file first so there's something on disk.
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();
    $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => UploadedFile::fake()->image('mine.jpg')],
    )->assertCreated();

    // Now act as the employee themselves (link an ordinary user to that
    // employee row — the download guard checks user_id === employee->user_id).
    $self = User::factory()->create(['employee_id' => $employee->id]);
    $employee->forceFill(['user_id' => $self->id])->save();
    Sanctum::actingAs($self);

    $signed = URL::temporarySignedRoute(
        'employees.national-id.download',
        now()->addMinutes(5),
        ['employee' => $employee->id],
    );

    $this->get($signed)->assertOk();
});

test('a peer employee cannot download another employee\'s national ID image', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();
    $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => UploadedFile::fake()->image('target.jpg')],
    )->assertCreated();

    $peer = User::factory()->create();
    Sanctum::actingAs($peer);

    $signed = URL::temporarySignedRoute(
        'employees.national-id.download',
        now()->addMinutes(5),
        ['employee' => $employee->id],
    );

    $this->get($signed)->assertForbidden();
});

test('deleting the national ID image clears the column and removes the file', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create();

    $path = $this->postJson(
        "/api/employees/{$employee->id}/national-id-image",
        ['file' => UploadedFile::fake()->image('id.jpg')],
    )->json('data.path');

    $this->deleteJson("/api/employees/{$employee->id}/national-id-image")
        ->assertNoContent();

    expect($employee->fresh()->national_id_image_path)->toBeNull();
    Storage::disk(EmployeeFileService::DISK)->assertMissing($path);
});

test('two employees cannot share one national ID', function () {
    Employee::factory()->create(['national_id' => '199912345']);

    expect(fn () => Employee::factory()->create(['national_id' => '199912345']))
        ->toThrow(QueryException::class);
});

test('national ID is persisted and surfaced on the resource', function () {
    $this->actingAsSuperAdmin();
    $schedule = WorkSchedule::factory()->create();

    $response = $this->postJson('/api/employees', [
        'first_name' => 'Rami',
        'last_name' => 'Haddad',
        'national_id' => '199988776',
        'employment_type' => 'full_time',
        'joining_date' => '2026-01-15',
        'work_schedule_id' => $schedule->id,
    ]);

    $response->assertCreated()->assertJsonPath('data.national_id', '199988776');
});

test('birth date on an existing employee is still returned (regression)', function () {
    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create(['birth_date' => '1995-05-15']);

    $this->getJson("/api/employees/{$employee->id}")
        ->assertOk()
        ->assertJsonPath('data.birth_date', '1995-05-15');
});
