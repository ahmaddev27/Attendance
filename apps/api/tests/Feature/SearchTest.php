<?php

declare(strict_types=1);

use App\Models\Employee;

/*
 * Feature tests for the global cross-index search endpoint.
 *
 * Tests deliberately swap Scout to its `collection` driver so no live
 * Meilisearch is required — the driver ships with Scout and runs the
 * search over an in-memory Eloquent collection. That's enough to prove
 * the controller/service wiring; index-level ranking behaviour is
 * covered by Meilisearch's own suite.
 */

beforeEach(function () {
    config()->set('scout.driver', 'collection');
    config()->set('scout.queue', false);

    actingAsAdmin();
});

test('returns matching employees for a known term', function () {
    Employee::factory()->create(['first_name' => 'Ahmad', 'last_name' => 'Admin']);
    Employee::factory()->create(['first_name' => 'Layla', 'last_name' => 'Nassar']);

    $response = $this->getJson('/api/search?q='.urlencode('Admin'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['employees', 'tasks', 'requests', 'leaves'],
        ]);

    expect($response->json('data.employees'))->not->toBeEmpty();
    expect($response->json('data.employees.0.type'))->toBe('employee');
    expect($response->json('data.employees.0.title'))->toContain('Admin');
});

test('returns empty arrays but 200 OK for an unknown term', function () {
    Employee::factory()->create(['first_name' => 'Layla', 'last_name' => 'Nassar']);

    $response = $this->getJson('/api/search?q='.urlencode('ZZZ-no-such-thing-1234'));

    $response->assertOk()
        ->assertExactJson([
            'data' => [
                'employees' => [],
                'tasks' => [],
                'requests' => [],
                'leaves' => [],
            ],
        ]);
});

test('returns empty arrays for a blank query without hitting the engine', function () {
    Employee::factory()->count(3)->create();

    $response = $this->getJson('/api/search?q=');

    $response->assertOk()
        ->assertJsonPath('data.employees', [])
        ->assertJsonPath('data.tasks', [])
        ->assertJsonPath('data.requests', [])
        ->assertJsonPath('data.leaves', []);
});

test('requires authentication', function () {
    // Wipe the sanctum guard set up by actingAsAdmin() in beforeEach.
    auth()->forgetGuards();

    $this->getJson('/api/search?q=anything')->assertUnauthorized();
});
