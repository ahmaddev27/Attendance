<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

uses(\Tests\Feature\Concerns\CreatesSuperAdmin::class);

/**
 * Exercises the admin bulk-email + bulk-SMS endpoints:
 *   - fan-out counts queued vs skipped-no-email/no-phone
 *   - verifies Notification::fake sees the right recipients
 *   - verifies the rate-limiter stops the 4th call per hour
 *   - verifies the 500-cap and the 403 for non-admin
 *
 * The limiter uses the cache store (array in tests). Each test gets a
 * fresh cache so the "4th call" test never collides with earlier tests.
 */
test('bulk email queues one send per employee with a resolvable email', function () {
    Notification::fake();
    config()->set('mail.default', 'resend'); // real-looking transport
    $this->actingAsSuperAdmin();

    $withEmail = Employee::factory()->count(3)->create();
    foreach ($withEmail as $e) {
        $u = User::factory()->create(['employee_id' => $e->id]);
        $e->forceFill(['user_id' => $u->id])->save();
    }

    // Two more with no email AND no linked user — service skips them.
    $withoutEmail = Employee::factory()->count(2)->create(['email' => null]);

    $ids = $withEmail->pluck('id')->merge($withoutEmail->pluck('id'))->all();

    $response = $this->postJson('/api/admin/employees/bulk-email', [
        'employee_ids' => $ids,
        'subject' => 'إشعار إداري',
        'body' => 'تذكير بموعد الاجتماع العام غداً الساعة 10 صباحاً.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.queued', 3)
        ->assertJsonPath('data.skipped_no_email', 2);

    // Each of the three resolvable employees received exactly one Taqat
    // notification.
    foreach ($withEmail as $e) {
        Notification::assertSentTo($e->user, TaqatNotification::class);
    }
});

test('bulk sms queues one send per employee with a phone', function () {
    // Stub the SMS gateway so we never leave the test process.
    $this->mock(SmsGateway::class, function (MockInterface $m) {
        $m->shouldReceive('send')->andReturn(SmsResult::success('msg-id', ['stub' => true]));
    });

    $this->actingAsSuperAdmin();

    $withPhone = Employee::factory()->count(4)->create(['phone' => '0599123456']);
    $withoutPhone = Employee::factory()->create(['phone' => null]);

    $ids = $withPhone->pluck('id')->merge([$withoutPhone->id])->all();

    $response = $this->postJson('/api/admin/employees/bulk-sms', [
        'employee_ids' => $ids,
        'body' => 'تذكير بموعد الاجتماع غداً 10 صباحاً.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.queued', 4)
        ->assertJsonPath('data.skipped_no_phone', 1);
});

test('a fourth bulk sms call in the hour is throttled', function () {
    $this->mock(SmsGateway::class, function (MockInterface $m) {
        $m->shouldReceive('send')->andReturn(SmsResult::success('msg-id', ['stub' => true]));
    });

    $this->actingAsSuperAdmin();
    $employee = Employee::factory()->create(['phone' => '0599123456']);

    $payload = [
        'employee_ids' => [$employee->id],
        'body' => 'رسالة اختبار.',
    ];

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/admin/employees/bulk-sms', $payload)->assertOk();
    }

    $this->postJson('/api/admin/employees/bulk-sms', $payload)
        ->assertStatus(429);
});

test('bulk email rejects more than 500 employee_ids', function () {
    $this->actingAsSuperAdmin();

    $ids = range(1, 501);

    $this->postJson('/api/admin/employees/bulk-email', [
        'employee_ids' => $ids,
        'subject' => 'x',
        'body' => 'y',
    ])->assertUnprocessable()->assertJsonValidationErrors('employee_ids');
});

test('bulk sms rejects more than 500 employee_ids', function () {
    $this->actingAsSuperAdmin();

    $ids = range(1, 501);

    $this->postJson('/api/admin/employees/bulk-sms', [
        'employee_ids' => $ids,
        'body' => 'y',
    ])->assertUnprocessable()->assertJsonValidationErrors('employee_ids');
});

test('non-admin cannot call bulk-email', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $employee = Employee::factory()->create();

    $this->postJson('/api/admin/employees/bulk-email', [
        'employee_ids' => [$employee->id],
        'subject' => 'x',
        'body' => 'y',
    ])->assertForbidden();
});

test('non-admin cannot call bulk-sms', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $employee = Employee::factory()->create();

    $this->postJson('/api/admin/employees/bulk-sms', [
        'employee_ids' => [$employee->id],
        'body' => 'y',
    ])->assertForbidden();
});
