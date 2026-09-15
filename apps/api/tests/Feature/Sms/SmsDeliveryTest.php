<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\SmsLog;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Sms\Gateways\MtcSmsGateway;
use App\Modules\Sms\Jobs\SendSmsJob;
use App\Modules\Sms\Notifications\Channels\SmsChannel;
use App\Modules\Sms\Services\SmsService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

/**
 * Reported 2026-09-15: the welcome SMS never arrived after creating an
 * employee. The phone was sent exactly as typed (local 059...) and, with
 * the endpoint setting left blank, the gateway posted to a host that does
 * not exist. MTCSMS's real HTTPS endpoint is int.mtcsms.com.
 */
test('the welcome sms goes to the international form of a locally typed phone', function () {
    Queue::fake();
    $this->actingAsSuperAdmin();
    Role::findOrCreate('employee', 'web');

    $this->postJson('/api/employees', [
        'first_name' => 'Sara',
        'last_name' => 'Haddad',
        'phone' => '059 912 3456',
        'employment_type' => 'full_time',
        'joining_date' => '2026-09-01',
        'work_schedule_id' => WorkSchedule::factory()->create()->id,
    ])->assertCreated();

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job) => $job->to === '970599123456');
});

test('the default country code comes from the sms settings', function () {
    Queue::fake();
    app(SettingsService::class)->set('sms.default_country_code', '972', 'sms');

    app(SmsService::class)->send('0599123456', 'hello');

    Queue::assertPushed(SendSmsJob::class, fn (SendSmsJob $job) => $job->to === '972599123456');
});

test('an unusable phone is logged as failed and never reaches the carrier', function () {
    Queue::fake();

    $queued = app(SmsService::class)->send('call me', 'hello');
    $inline = app(SmsService::class)->sendNow('12', 'hello');

    Queue::assertNothingPushed();

    expect($queued->success)->toBeFalse()
        ->and($queued->error)->toBe('invalid_phone')
        ->and($inline->error)->toBe('invalid_phone')
        ->and(SmsLog::query()->where('status', 'failed')->where('error', 'invalid_phone')->count())->toBe(2);
});

test('an inline send logs the number that was actually dialled', function () {
    app(SmsService::class)->sendNow('0599123456', 'hello');

    expect(SmsLog::query()->sole()->to)->toBe('970599123456');
});

test('with no endpoint configured the gateway posts to the MTCSMS https host', function () {
    Http::fake(['https://int.mtcsms.com/*' => Http::response('0@msg-1', 200)]);

    $result = (new MtcSmsGateway(username: 'acct', password: 'secret', sender: 'TAQAT', endpoint: ''))
        ->send('970599123456', 'hello');

    expect($result->success)->toBeTrue()
        ->and($result->provider_message_id)->toBe('msg-1');

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://int.mtcsms.com/sendsms.aspx'
        && $request['to'] === '970599123456'
        && (string) $request['type'] === '0');
});

test('arabic messages use the unicode type configured for the account', function () {
    Http::fake(['https://int.mtcsms.com/*' => Http::response('0@msg-2', 200)]);

    (new MtcSmsGateway(username: 'acct', password: 'secret', endpoint: '', unicodeType: 2))
        ->send('970599123456', 'مرحباً');

    Http::assertSent(fn (HttpRequest $request) => (string) $request['type'] === '2');
});

test('notification sms is sendable when the MTC credentials were saved from the settings page', function () {
    Notification::fake();
    config()->set('services.mtc_sms.username', null);
    config()->set('services.mtc_sms.fake', false);
    app(SettingsService::class)->set('sms.mtc_username', 'acct', 'sms');

    $employee = Employee::factory()->create(['phone' => '0599123456']);
    $user = User::factory()->create(['employee_id' => $employee->id]);

    $user->notify(new TaqatNotification(title: 'Leave approved', body: 'enjoy', sendSms: true));

    Notification::assertSentTo(
        $user,
        TaqatNotification::class,
        fn (TaqatNotification $notification, array $channels): bool => in_array(SmsChannel::class, $channels, true),
    );
});
