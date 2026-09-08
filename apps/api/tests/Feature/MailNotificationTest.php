<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

/**
 * Covers the mail channel wiring on TaqatNotification:
 *   - it opts into 'mail' when a real transport is configured AND the
 *     recipient has an email;
 *   - suppressMail=true excludes 'mail' but still lands in database;
 *   - `log` / `array` transports keep 'mail' out (so phpunit and local
 *     dev never queue outbound email).
 *
 * Uses Notification::fake() so nothing actually hits Resend.
 */
beforeEach(function (): void {
    // TaqatNotification::via() reads config('mail.default') at runtime —
    // flip it here to simulate a production-shaped mail configuration.
    config()->set('mail.default', 'resend');
    // Broadcast off keeps assertions focused on database + mail.
    config()->set('broadcasting.default', 'null');
    config()->set('app.url', 'https://taqat.test');
});

test('mail channel is included when a real transport is configured', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'employee@taqat.test']);

    $notification = new TaqatNotification(
        title: 'تمت الموافقة على طلب إجازتك',
        body: 'إجازة سنوية — من 2026-09-10 إلى 2026-09-12',
        url: '/my-leaves',
        icon: 'check-circle',
        meta: ['leave_request_id' => 42],
    );

    Notification::send($user, $notification);

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        function (TaqatNotification $sent, array $channels) use ($user): bool {
            expect($channels)->toContain('mail')->toContain('database');

            $mail = $sent->toMail($user);
            expect($mail)->toBeInstanceOf(MailMessage::class);
            expect($mail->subject)->toBe('تمت الموافقة على طلب إجازتك');
            expect($mail->actionUrl)->toBe('https://taqat.test/my-leaves');
            expect($mail->actionText)->toBe('عرض التفاصيل');

            return true;
        },
    );
});

test('suppressMail=true keeps database but drops the mail channel', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'employee@taqat.test']);

    Notification::send($user, new TaqatNotification(
        title: 'طلب جديد بانتظار موافقتك',
        body: 'طلب إجازة — REQ-0001',
        url: '/approvals',
        suppressMail: true,
    ));

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        function (TaqatNotification $sent, array $channels): bool {
            expect($channels)->toContain('database');
            expect($channels)->not->toContain('mail');

            return true;
        },
    );
});

test('mail channel is excluded when MAIL_MAILER=log (dev/CI default)', function (): void {
    config()->set('mail.default', 'log');
    Notification::fake();

    $user = User::factory()->create(['email' => 'employee@taqat.test']);

    Notification::send($user, new TaqatNotification(
        title: 'إشعار تجريبي',
        body: 'رسالة تجريبية',
    ));

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        function (TaqatNotification $sent, array $channels): bool {
            expect($channels)->toContain('database');
            expect($channels)->not->toContain('mail');

            return true;
        },
    );
});

