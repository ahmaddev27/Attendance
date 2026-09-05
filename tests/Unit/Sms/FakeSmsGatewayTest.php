<?php

use App\Services\Sms\FakeSmsGateway;

it('records sent messages', function () {
    $gateway = new FakeSmsGateway();
    $result = $gateway->send('+962700000000', 'Hello');

    expect($result->ok)->toBeTrue();
    expect($gateway->sent())->toHaveCount(1);
    expect($gateway->sent()[0]['to'])->toBe('+962700000000');
});

it('can be configured to fail', function () {
    $gateway = new FakeSmsGateway();
    $gateway->shouldFail('10005');
    $result = $gateway->send('+962700000000', 'Hello');

    expect($result->ok)->toBeFalse();
    expect($result->errorCode)->toBe('10005');
});
