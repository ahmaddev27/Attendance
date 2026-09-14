<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Recruitment\Concerns\SeedsRecruitmentPermissions;

uses(SeedsRecruitmentPermissions::class);

/**
 * The owner pickers on the case, job and lead screens choose people by name
 * from this list instead of asking for a raw user id.
 */

/**
 * @param  array<string, mixed>  $attributes
 * @param  list<string>  $permissions
 */
function createRecruitmentPickerUser(array $attributes = [], array $permissions = []): User
{
    $user = User::factory()->create($attributes);

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

/**
 * @return list<int>
 */
function recruitmentPickerIds(string $uri): array
{
    return collect(test()->getJson($uri)->assertOk()->json('data'))->pluck('id')->all();
}

test('staff without a recruitment view permission cannot list assignable users', function (array $permissions) {
    $this->actingAsUserWithPermissions($permissions);

    $this->getJson('/api/recruitment/users')->assertForbidden();
})->with([
    'no permissions' => [[]],
    'recruitment work but no view permission' => [['manage-jobs', 'screen-candidates']],
]);

test('any recruitment view permission can list assignable users', function (string $permission) {
    $this->actingAsUserWithPermissions([$permission]);

    $this->getJson('/api/recruitment/users')->assertOk();
})->with(['view-leads', 'view-clients', 'view-recruitment-cases', 'view-jobs']);

test('lists users holding a recruitment permission directly, through a role, or as super-admin', function () {
    $viewer = $this->actingAsUserWithPermissions(['view-leads']);
    $direct = createRecruitmentPickerUser(permissions: ['manage-jobs']);

    Role::findOrCreate('recruiter', 'web')->givePermissionTo('screen-candidates');
    $recruiter = User::factory()->create();
    $recruiter->assignRole('recruiter');

    // Nothing is synced onto this role: super-admins qualify by role name alone.
    Role::findOrCreate('super-admin', 'web');
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    expect(recruitmentPickerIds('/api/recruitment/users'))
        ->toEqualCanonicalizing([$viewer->id, $direct->id, $recruiter->id, $superAdmin->id]);
});

test('each option carries only the id, name and employee number', function () {
    $viewer = $this->actingAsUserWithPermissions(['view-jobs']);

    $this->getJson('/api/recruitment/users')
        ->assertOk()
        ->assertExactJson(['data' => [[
            'id' => $viewer->id,
            'name' => $viewer->name,
            'employee_number' => $viewer->employee_number,
        ]]]);
});

test('inactive users are left out even when they hold recruitment access', function () {
    $viewer = $this->actingAsUserWithPermissions(['view-recruitment-cases']);
    createRecruitmentPickerUser(['is_active' => false], ['manage-recruitment-cases']);

    Role::findOrCreate('super-admin', 'web');
    User::factory()->inactive()->create()->assignRole('super-admin');

    expect(recruitmentPickerIds('/api/recruitment/users'))->toBe([$viewer->id]);
});

test('users without any recruitment permission are left out', function () {
    $viewer = $this->actingAsUserWithPermissions(['view-clients']);

    createRecruitmentPickerUser(permissions: ['manage-users']);

    Role::findOrCreate('hr-manager', 'web')->givePermissionTo(Permission::findOrCreate('approve-leaves', 'web'));
    User::factory()->create()->assignRole('hr-manager');

    User::factory()->create();

    expect(recruitmentPickerIds('/api/recruitment/users'))->toBe([$viewer->id]);
});

test('search matches a name, an email or an exact employee number', function () {
    $viewer = $this->actingAsUserWithPermissions(['view-leads']);
    $viewer->update(['name' => 'Viewer Account', 'email' => 'viewer.account@example.test']);

    $zeina = createRecruitmentPickerUser(
        ['name' => 'Zeina Haddad', 'email' => 'zeina.h@example.test'],
        ['manage-leads'],
    );
    $omar = createRecruitmentPickerUser(
        ['name' => 'Omar Khalil', 'email' => 'omar.recruiter@example.test'],
        ['manage-leads'],
    );
    $lina = createRecruitmentPickerUser(
        ['name' => 'Lina Saleh', 'email' => 'lina.s@example.test', 'employee_number' => 1000321],
        ['manage-leads'],
    );

    expect(recruitmentPickerIds('/api/recruitment/users?search=haddad'))->toBe([$zeina->id])
        ->and(recruitmentPickerIds('/api/recruitment/users?search=omar.recruiter'))->toBe([$omar->id])
        ->and(recruitmentPickerIds('/api/recruitment/users?search=1000321'))->toBe([$lina->id])
        ->and(recruitmentPickerIds('/api/recruitment/users?search=nobody-matches'))->toBe([]);
});

test('search terms longer than 100 characters are rejected', function () {
    $this->actingAsUserWithPermissions(['view-leads']);

    $this->getJson('/api/recruitment/users?search='.str_repeat('a', 101))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search']);
});

test('results are ordered by name and capped at fifty', function () {
    $viewer = $this->actingAsUserWithPermissions(['view-jobs']);
    $viewer->update(['name' => 'Picker 00']);

    User::factory()
        ->count(55)
        ->sequence(fn ($sequence) => ['name' => sprintf('Picker %02d', $sequence->index + 1)])
        ->create()
        ->each(fn (User $user) => $user->givePermissionTo('manage-jobs'));

    $names = collect(
        $this->getJson('/api/recruitment/users')->assertOk()->assertJsonCount(50, 'data')->json('data')
    )->pluck('name');

    expect($names->first())->toBe('Picker 00')
        ->and($names->last())->toBe('Picker 49')
        ->and($names->all())->toBe($names->sort()->values()->all());
});
