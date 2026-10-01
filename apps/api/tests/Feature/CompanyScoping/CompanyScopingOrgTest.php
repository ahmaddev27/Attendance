<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

beforeEach(function (): void {
    $this->actingAsSuperAdmin();
    Role::findOrCreate('employee', 'web');
});

/**
 * Organization filters — Soft Company Scoping follow-up.
 *
 * The dashboard + employee / attendance / leaves list endpoints shipped
 * with ?company_id in c856558. Departments and teams were missed —
 * `GET /org/departments` and `GET /org/teams` returned every row across
 * every company, so the admin UI's filter switcher couldn't actually
 * narrow those two admin pages. This file guards the follow-up.
 */

// -----------------------------------------------------------------------
// Departments — direct `company_id` column, so the repository applies a
// bare where() clause (no subquery).
// -----------------------------------------------------------------------

test('departments list filters by company_id', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    // makeOrgChain already creates one department per company; add a
    // second to A so the filter actually has multiple rows to narrow.
    Department::factory()->count(2)->create(['company_id' => $orgA['company']->id]);
    Department::factory()->count(4)->create(['company_id' => $orgB['company']->id]);

    $this->getJson("/api/org/departments?company_id={$orgA['company']->id}")
        ->assertOk()
        // 1 from makeOrgChain + 2 factory count = 3 in company A
        ->assertJsonPath('meta.total', 3);
});

test('departments list without company_id returns every company', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    Department::factory()->count(2)->create(['company_id' => $orgA['company']->id]);
    Department::factory()->count(4)->create(['company_id' => $orgB['company']->id]);

    $this->getJson('/api/org/departments')
        ->assertOk()
        // 2 from makeOrgChain + 2 + 4 = 8 total
        ->assertJsonPath('meta.total', 8);
});

test('departments list rejects an unknown company_id with 422', function (): void {
    $this->getJson('/api/org/departments?company_id=99999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_id');
});

test('creating a department with explicit company_id honors the value', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    $response = $this->postJson('/api/org/departments', [
        'name' => 'Engineering',
        'company_id' => $orgB['company']->id,
    ])->assertCreated();

    $departmentId = (int) $response->json('data.id');

    expect(Department::query()->find($departmentId)->company_id)->toBe($orgB['company']->id);
});

// -----------------------------------------------------------------------
// Teams — NO direct `company_id` column. The repository filters via a
// subquery against departments.company_id.
// -----------------------------------------------------------------------

test('teams list filters by company_id via departments', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    // Each makeOrgChain already seeds one team. Add two more teams under
    // orgA's department and one more under orgB's.
    Team::factory()->count(2)->create(['department_id' => $orgA['department']->id]);
    Team::factory()->count(1)->create(['department_id' => $orgB['department']->id]);

    $this->getJson("/api/org/teams?company_id={$orgA['company']->id}")
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
});

test('teams list without company_id returns every team', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    Team::factory()->count(2)->create(['department_id' => $orgA['department']->id]);
    Team::factory()->count(1)->create(['department_id' => $orgB['department']->id]);

    $this->getJson('/api/org/teams')
        ->assertOk()
        // 2 from makeOrgChain + 2 + 1 = 5 total
        ->assertJsonPath('meta.total', 5);
});

test('teams list rejects an unknown company_id with 422', function (): void {
    $this->getJson('/api/org/teams?company_id=99999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_id');
});

test('creating a team with a department resolves to that departments company', function (): void {
    $orgA = makeOrgChain('A');

    $response = $this->postJson('/api/org/teams', [
        'name' => 'Platform',
        'department_id' => $orgA['department']->id,
    ])->assertCreated();

    $teamId = (int) $response->json('data.id');

    // The team itself has no company_id column; the invariant we care
    // about is that its department is in the expected company.
    $team = Team::query()->with('department')->findOrFail($teamId);
    expect($team->department->company_id)->toBe($orgA['company']->id);
});

// -----------------------------------------------------------------------
// Cross-check — a cross-company filter combined with a department_id that
// belongs to a DIFFERENT company must return zero rows (no leaks from the
// department_id filter overriding the company scope).
// -----------------------------------------------------------------------

test('teams list applies both department_id and company_id filters simultaneously', function (): void {
    $orgA = makeOrgChain('A');
    $orgB = makeOrgChain('B');

    Team::factory()->count(2)->create(['department_id' => $orgA['department']->id]);

    // Combine orgA's department with orgB's company — zero intersection.
    $this->getJson(
        "/api/org/teams?company_id={$orgB['company']->id}&department_id={$orgA['department']->id}"
    )
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});
