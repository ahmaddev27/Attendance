<?php

declare(strict_types=1);

use App\Modules\Sms\Gateways\FakeSmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * FakeSmsGateway is the driver used in tests + local dev; it must
 * never touch the network and must always report success. These tests
 * pin both contracts and the laravel.log write, since NotificationService
 * relies on the log line for local smoke debugging.
 */
test('send() returns success with a fake provider_message_id', function () {
    $gateway = new FakeSmsGateway();

    $result = $gateway->send('0791234567', 'hello world');

    expect($result->success)->toBeTrue()
        ->and($result->provider_message_id)->toBeString()
        ->and($result->provider_message_id)->toStartWith('fake-')
        ->and($result->error)->toBeNull()
        ->and($result->raw_response)->toBeArray()
        ->and($result->raw_response['driver'] ?? null)->toBe('fake');
});

test('send() logs the payload to laravel.log', function () {
    Log::spy();

    $gateway = new FakeSmsGateway();
    $gateway->send('0791234567', 'hello from test');

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'FakeSmsGateway')
                && ($context['to'] ?? null) === '0791234567'
                && ($context['body'] ?? null) === 'hello from test'
                && str_starts_with((string) ($context['provider_message_id'] ?? ''), 'fake-');
        });
});
