<?php

use App\Models\SmsLog;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use App\Services\Sms\SmsService;

beforeEach(function () {
    $this->fake = new FakeSmsGateway();
    $this->app->instance(SmsGatewayInterface::class, $this->fake);
});

it('sends and logs success', function () {
    app(SmsService::class)->sendNow('+962700000000', 'hello');

    expect($this->fake->sent())->toHaveCount(1);
    expect(SmsLog::where('status', 'sent')->count())->toBe(1);
});

it('logs failure with error code', function () {
    $this->fake->shouldFail('10005');
    app(SmsService::class)->sendNow('+962700000000', 'hello');

    $log = SmsLog::first();
    expect($log->status->value)->toBe('failed');
    expect($log->error_code)->toBe('10005');
});
