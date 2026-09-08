<?php

declare(strict_types=1);

use App\Modules\Whatsapp\Gateways\FakeWhatsappGateway;
use Illuminate\Support\Facades\Log;

/**
 * FakeWhatsappGateway is the driver used in tests + local dev; it must
 * never touch the network and must always report success. These tests
 * pin both contracts and the laravel.log write, since operators rely
 * on the log line for local smoke debugging.
 */
test('send() returns success with a fake provider_message_id', function () {
    $gateway = new FakeWhatsappGateway();

    $result = $gateway->send('+962791234567', 'hello world');

    expect($result->success)->toBeTrue()
        ->and($result->provider_message_id)->toBeString()
        ->and($result->provider_message_id)->toStartWith('fake-wa-')
        ->and($result->error)->toBeNull()
        ->and($result->raw_response)->toBeArray()
        ->and($result->raw_response['driver'] ?? null)->toBe('fake');
});

test('send() logs the payload to laravel.log', function () {
    Log::spy();

    $gateway = new FakeWhatsappGateway();
    $gateway->send('+962791234567', 'hello from test');

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'FakeWhatsappGateway')
                && ($context['to'] ?? null) === '+962791234567'
                && ($context['body'] ?? null) === 'hello from test'
                && str_starts_with((string) ($context['provider_message_id'] ?? ''), 'fake-wa-');
        });
});
