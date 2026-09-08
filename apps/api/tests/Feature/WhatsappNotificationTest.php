<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Modules\Whatsapp\Notifications\Channels\WhatsappChannel;
use Illuminate\Support\Facades\Notification;

/**
 * The channel list is dynamic — via() branches on the sendWhatsapp
 * flag, the WhatsApp config, and whether the notifiable's employee has
 * a phone. These tests pin all three branches so a future refactor of
 * via() can't silently start (or stop) sending WhatsApp messages.
 */
beforeEach(function (): void {
    // FakeWhatsappGateway is bound automatically in the testing env,
    // but via() only opts WhatsApp in when *credentials* are present
    // too. Set both here (each test overrides as needed) so the happy
    // path fires.
    config()->set('services.whatsapp.access_token', 'test-token');
    config()->set('services.whatsapp.phone_number_id', '123456789');
});

test('notification with sendWhatsapp=true is delivered via WhatsappChannel to an employee with a phone', function () {
    Notification::fake();

    $employee = Employee::factory()->create(['phone' => '+962791234567']);
    $user = User::factory()->create(['employee_id' => $employee->id]);

    $notification = new TaqatNotification(
        title: 'Test title',
        body: 'Test body over WhatsApp',
        sendWhatsapp: true,
    );

    $user->notify($notification);

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $n, array $channels): bool => in_array(WhatsappChannel::class, $channels, true),
    );
});

test('notification with sendWhatsapp=false does NOT include WhatsappChannel in via()', function () {
    Notification::fake();

    $employee = Employee::factory()->create(['phone' => '+962791234567']);
    $user = User::factory()->create(['employee_id' => $employee->id]);

    $notification = new TaqatNotification(
        title: 'Regular update',
        body: 'no WhatsApp please',
        sendWhatsapp: false,
    );

    $user->notify($notification);

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $n, array $channels): bool => ! in_array(WhatsappChannel::class, $channels, true),
    );
});

test('notification with sendWhatsapp=true silently skips WhatsApp when credentials are not configured', function () {
    Notification::fake();

    // Wipe both the real credentials and the fake toggle — via() must
    // now treat WhatsApp as unavailable and drop the channel silently.
    config()->set('services.whatsapp.access_token', null);
    config()->set('services.whatsapp.phone_number_id', null);
    config()->set('services.whatsapp.fake', false);

    $employee = Employee::factory()->create(['phone' => '+962791234567']);
    $user = User::factory()->create(['employee_id' => $employee->id]);

    $user->notify(new TaqatNotification(
        title: 'Would-be WhatsApp',
        body: 'but no gateway configured',
        sendWhatsapp: true,
    ));

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $n, array $channels): bool => ! in_array(WhatsappChannel::class, $channels, true),
    );
});

test('notification with sendWhatsapp=true silently skips WhatsApp when the recipient has no employee phone', function () {
    Notification::fake();

    // Employee-less user (an admin account, for instance). via() must
    // omit WhatsappChannel because there's nothing to dial.
    $user = User::factory()->create(['employee_id' => null]);

    $user->notify(new TaqatNotification(
        title: 'No phone',
        body: 'nothing to dial',
        sendWhatsapp: true,
    ));

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $n, array $channels): bool => ! in_array(WhatsappChannel::class, $channels, true),
    );
});
