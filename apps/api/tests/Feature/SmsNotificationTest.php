<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Modules\Sms\Notifications\Channels\SmsChannel;
use Illuminate\Support\Facades\Notification;

/**
 * The channel list is dynamic — via() branches on the sendSms flag, the
 * MTC config, and whether the notifiable's employee has a phone. These
 * tests pin all three branches so a future refactor of via() can't
 * silently start (or stop) sending SMS.
 */
beforeEach(function (): void {
    // FakeSmsGateway is bound automatically in the testing env, but
    // via() only opts SMS in when *credentials* are present too. Set
    // both here (each test overrides as needed) so the happy path fires.
    config()->set('services.mtc_sms.username', 'test-user');
    config()->set('services.mtc_sms.password', 'test-pass');
});

test('notification with sendSms=true is delivered via SmsChannel to an employee with a phone', function () {
    Notification::fake();

    $employee = Employee::factory()->create(['phone' => '0791234567']);
    $user = User::factory()->create(['employee_id' => $employee->id]);

    $notification = new TaqatNotification(
        title: 'OTP',
        body: 'Your code is 1234',
        sendSms: true,
    );

    $user->notify($notification);

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $n, array $channels): bool => in_array(SmsChannel::class, $channels, true),
    );
});

test('notification with sendSms=false does NOT include SmsChannel in via()', function () {
    Notification::fake();

    $employee = Employee::factory()->create(['phone' => '0791234567']);
    $user = User::factory()->create(['employee_id' => $employee->id]);

    $notification = new TaqatNotification(
        title: 'Regular update',
        body: 'no SMS please',
        sendSms: false,
    );

    $user->notify($notification);

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $n, array $channels): bool => ! in_array(SmsChannel::class, $channels, true),
    );
});

test('notification with sendSms=true silently skips SMS when MTC credentials are not configured', function () {
    Notification::fake();

    // Wipe both the real credentials and the fake toggle — via() must
    // now treat SMS as unavailable and drop the channel silently.
    config()->set('services.mtc_sms.username', null);
    config()->set('services.mtc_sms.password', null);
    config()->set('services.mtc_sms.fake', false);

    $employee = Employee::factory()->create(['phone' => '0791234567']);
    $user = User::factory()->create(['employee_id' => $employee->id]);

    $user->notify(new TaqatNotification(
        title: 'Would-be SMS',
        body: 'but no gateway configured',
        sendSms: true,
    ));

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $n, array $channels): bool => ! in_array(SmsChannel::class, $channels, true),
    );
});

test('notification with sendSms=true silently skips SMS when the recipient has no employee phone', function () {
    Notification::fake();

    // Employee-less user (an admin account, for instance). via() must
    // omit SmsChannel because there's nothing to dial.
    $user = User::factory()->create(['employee_id' => null]);

    $user->notify(new TaqatNotification(
        title: 'No phone',
        body: 'nothing to dial',
        sendSms: true,
    ));

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $n, array $channels): bool => ! in_array(SmsChannel::class, $channels, true),
    );
});
