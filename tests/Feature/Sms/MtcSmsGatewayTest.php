<?php

use App\Services\Sms\MtcSmsGateway;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app(SettingsService::class)->set('sms_username', 'user1', 'string');
    app(SettingsService::class)->set('sms_password', 'pass1', 'string');
    app(SettingsService::class)->set('sms_sender', 'ACME', 'string');
});

it('returns success when provider returns 0', function () {
    Http::fake(['int.mtcsms.com/*' => Http::response('0@Message Sent Successfully', 200)]);
    $result = app(MtcSmsGateway::class)->send('+962700000000', 'hi');

    expect($result->ok)->toBeTrue();
});

it('returns failure with error code when provider returns 10005', function () {
    Http::fake(['int.mtcsms.com/*' => Http::response('10005@Low Balance', 200)]);
    $result = app(MtcSmsGateway::class)->send('+962700000000', 'hi');

    expect($result->ok)->toBeFalse();
    expect($result->errorCode)->toBe('10005');
});

it('returns failure when HTTP call fails', function () {
    Http::fake(['int.mtcsms.com/*' => Http::response('', 500)]);
    $result = app(MtcSmsGateway::class)->send('+962700000000', 'hi');

    expect($result->ok)->toBeFalse();
});
