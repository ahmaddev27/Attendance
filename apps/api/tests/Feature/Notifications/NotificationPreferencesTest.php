<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\NotificationPreference;
use App\Models\Task;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\ActsAsEmployeeUser;

uses(ActsAsEmployeeUser::class);

/**
 * Opt-OUT model: no row means "enabled". The dispatch path reads the
 * matrix per channel BEFORE Notification::send fans out, so these tests
 * assert on the channel list each TaqatNotification was handed to.
 *
 * taskAssigned() is used as the probe event because it's the shortest
 * domain object to materialise in a test (one Task, one Employee→User
 * link) while still going through the real NotificationService::dispatch
 * code path we want to pin.
 */
beforeEach(function (): void {
    // Keep channels off by default so our assertions focus on the gate
    // itself; individual tests flip them back on when needed.
    config()->set('broadcasting.default', 'null');
    config()->set('mail.default', 'array');
});

/**
 * Build a user that owns an Employee row (so `->employee` resolves) plus
 * a Task assigned to that same employee — the minimum state
 * taskAssigned() needs to pick the user up.
 */
function makeAssignedTaskFor(User $user): Task
{
    $employee = $user->employee;
    expect($employee)->not->toBeNull();

    /** @var Task $task */
    $task = Task::factory()->create(['assigned_to' => $employee->id]);

    // Prime the two relations NotificationService walks
    // (`$task->assignee->user`) so the dispatch doesn't lazy-load mid-test.
    $task->setRelation('assignee', $employee->setRelation('user', $user));

    return $task;
}

test('default behaviour: notification fires on every applicable channel when no preference row exists', function (): void {
    Notification::fake();

    /** @var User $user */
    $user = $this->actingAsEmployeeUser(Employee::factory()->create());

    $task = makeAssignedTaskFor($user);

    app(NotificationService::class)->taskAssigned($task);

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        function (TaqatNotification $sent, array $channels): bool {
            expect($channels)->toContain('database');
            expect($sent->getEventKey())->toBe('task_assigned');

            return true;
        },
    );
});

test('disabling the broadcast channel suppresses only that channel; database still fires', function (): void {
    // Flip broadcast ON so the baseline would normally include it.
    config()->set('broadcasting.default', 'reverb');

    Notification::fake();

    /** @var User $user */
    $user = $this->actingAsEmployeeUser(Employee::factory()->create());

    // Opt out of the broadcast channel for this one event.
    app(NotificationPreferenceService::class)->set($user, 'task_assigned', 'broadcast', false);

    $task = makeAssignedTaskFor($user);

    app(NotificationService::class)->taskAssigned($task);

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        function (TaqatNotification $sent, array $channels): bool {
            expect($channels)->toContain('database');
            expect($channels)->not->toContain('broadcast');

            return true;
        },
    );
});

test('disabling every active channel still writes the database row (durable inbox)', function (): void {
    // Turn every outbound channel ON in config so the gate has something
    // to actually suppress.
    config()->set('broadcasting.default', 'reverb');
    config()->set('mail.default', 'smtp');
    config()->set('services.mtc_sms.fake', true);
    config()->set('services.whatsapp.fake', true);

    Notification::fake();

    /** @var User $user */
    $user = $this->actingAsEmployeeUser(Employee::factory()->create(['phone' => '0791234567']));

    // Silence everything except database.
    $service = app(NotificationPreferenceService::class);
    foreach (['broadcast', 'mail', 'sms', 'whatsapp', 'push'] as $ch) {
        $service->set($user, 'task_assigned', $ch, false);
    }

    $task = makeAssignedTaskFor($user);

    app(NotificationService::class)->taskAssigned($task);

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        function (TaqatNotification $sent, array $channels): bool {
            // The in-app bell must still catch up on the next poll — that
            // is the whole point of the opt-OUT model.
            expect($channels)->toContain('database');
            expect($channels)->not->toContain('broadcast');
            expect($channels)->not->toContain('mail');

            return true;
        },
    );
});

test('isEnabled defaults to true when no row exists and returns the stored value otherwise', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $service = app(NotificationPreferenceService::class);

    expect($service->isEnabled($user, 'task_assigned', 'mail'))->toBeTrue();

    $service->set($user, 'task_assigned', 'mail', false);
    expect($service->isEnabled($user, 'task_assigned', 'mail'))->toBeFalse();

    $service->set($user, 'task_assigned', 'mail', true);
    expect($service->isEnabled($user, 'task_assigned', 'mail'))->toBeTrue();
});

test('set() upserts in place — no duplicate rows per (user, event, channel)', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $service = app(NotificationPreferenceService::class);

    $service->set($user, 'task_assigned', 'push', false);
    $service->set($user, 'task_assigned', 'push', true);
    $service->set($user, 'task_assigned', 'push', false);

    expect(NotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('event_key', 'task_assigned')
        ->where('channel', 'push')
        ->count())->toBe(1);
});

test('GET /api/me/notification-preferences returns the full matrix with user overrides merged in', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    app(NotificationPreferenceService::class)->set($user, 'task_assigned', 'push', false);

    $response = $this->getJson('/api/me/notification-preferences');

    $response->assertOk();
    $payload = $response->json('data');

    expect($payload)->toBeArray();
    $taskAssigned = collect($payload)->firstWhere('event_key', 'task_assigned');
    expect($taskAssigned)->not->toBeNull();
    expect($taskAssigned['channels']['push'])->toBeFalse();
    // Untouched cell: opt-OUT default must be true.
    expect($taskAssigned['channels']['database'])->toBeTrue();

    // Every catalog entry must be in the response — the UI depends on it
    // to render the full matrix on first paint.
    $catalogKeys = array_keys(app(NotificationPreferenceService::class)->knownEventKeys());
    $responseKeys = array_map(static fn ($row) => $row['event_key'], $payload);
    foreach ($catalogKeys as $k) {
        expect($responseKeys)->toContain($k);
    }
});

test('PUT /api/me/notification-preferences bulk-upserts and 422s on an unknown key', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/me/notification-preferences', [
        'preferences' => [
            ['event_key' => 'leave_decided', 'channel' => 'mail', 'enabled' => false],
            ['event_key' => 'task_assigned', 'channel' => 'push', 'enabled' => false],
        ],
    ]);

    $response->assertOk();

    $service = app(NotificationPreferenceService::class);
    expect($service->isEnabled($user, 'leave_decided', 'mail'))->toBeFalse();
    expect($service->isEnabled($user, 'task_assigned', 'push'))->toBeFalse();

    // Idempotent upsert — one row per tuple.
    expect(NotificationPreference::query()->where('user_id', $user->id)->count())->toBe(2);

    // Unknown key is rejected rather than silently stored.
    $this->putJson('/api/me/notification-preferences', [
        'preferences' => [
            ['event_key' => 'not_a_real_event', 'channel' => 'mail', 'enabled' => false],
        ],
    ])->assertStatus(422);

    // Unknown channel is rejected too.
    $this->putJson('/api/me/notification-preferences', [
        'preferences' => [
            ['event_key' => 'leave_decided', 'channel' => 'carrier-pigeon', 'enabled' => false],
        ],
    ])->assertStatus(422);
});

test('both endpoints 401 for unauthenticated requests', function (): void {
    $this->getJson('/api/me/notification-preferences')->assertStatus(401);
    $this->putJson('/api/me/notification-preferences', [
        'preferences' => [
            ['event_key' => 'leave_decided', 'channel' => 'mail', 'enabled' => false],
        ],
    ])->assertStatus(401);
});

test('resolveEventKey() maps dedup prefixes to catalog keys and returns null for unknown prefixes', function (): void {
    $service = app(NotificationPreferenceService::class);

    expect($service->resolveEventKey('leave-decided:42'))->toBe('leave_decided');
    expect($service->resolveEventKey('request-pending:17'))->toBe('request_pending');
    expect($service->resolveEventKey('job-stage-advanced:9:3'))->toBe('job_stage_advanced');
    expect($service->resolveEventKey('something-brand-new:5'))->toBeNull();
    expect($service->resolveEventKey(''))->toBeNull();
});
