<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Settings\Services\SettingsService;
use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Gateways\FakeSmsGateway;
use App\Modules\Sms\Gateways\MtcSmsGateway;
use App\Modules\Push\Contracts\PushGateway;
use App\Modules\Push\Gateways\ExpoPushGateway;
use App\Modules\Push\Gateways\FakePushGateway;
use App\Modules\Whatsapp\Contracts\WhatsappGateway;
use App\Modules\Whatsapp\Gateways\FakeWhatsappGateway;
use App\Modules\Whatsapp\Gateways\MetaCloudGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->registerSmsGateway();
        $this->registerWhatsappGateway();
        $this->registerPushGateway();
    }

    /**
     * Bootstrap any application services.
     *
     * Applies the DB-backed admin settings (mail credentials, SMS creds)
     * on top of the .env baseline so an admin can rotate keys from the UI
     * without a redeploy. Runs inside a try/catch so a fresh install with
     * no settings table (before migrations run) still boots.
     */
    public function boot(): void
    {
        $this->applyMailSettingsOverrides();
    }

    /**
     * Bind the SmsGateway contract. The fake driver is used
     *   - in the `testing` environment (so phpunit runs never touch MTC), or
     *   - when the `sms.mtc_fake` setting is truthy (local dev + staging).
     * Everything else resolves the real MTC HTTP gateway with credentials
     * read from SettingsService (falling back to config → env).
     */
    private function registerSmsGateway(): void
    {
        $this->app->bind(SmsGateway::class, function (Application $app): SmsGateway {
            /** @var SettingsService $settings */
            $settings = $app->make(SettingsService::class);

            $fakeFlag = $this->safe(fn () => $settings->get('sms.mtc_fake', 'services.mtc_sms.fake', '0'));
            $useFake = $app->environment('testing')
                || filter_var($fakeFlag, FILTER_VALIDATE_BOOLEAN);

            if ($useFake) {
                return new FakeSmsGateway();
            }

            return new MtcSmsGateway(
                username: (string) $this->safe(fn () => $settings->get('sms.mtc_username', 'services.mtc_sms.username')),
                password: (string) $this->safe(fn () => $settings->get('sms.mtc_password', 'services.mtc_sms.password')),
                sender: (string) $this->safe(fn () => $settings->get('sms.mtc_sender', 'services.mtc_sms.sender', 'TAQAT')),
                endpoint: (string) $this->safe(fn () => $settings->get('sms.mtc_endpoint', 'services.mtc_sms.endpoint')),
                timeout: (int) config('services.mtc_sms.timeout', 10),
            );
        });
    }

    /**
     * Bind the WhatsappGateway contract. The fake driver is used
     *   - in the `testing` environment (so phpunit runs never touch Meta), or
     *   - when the `whatsapp.fake` setting is truthy (local dev + staging).
     * Everything else resolves the real Meta Cloud HTTP gateway with
     * credentials read from SettingsService (falling back to config → env).
     */
    private function registerWhatsappGateway(): void
    {
        $this->app->bind(WhatsappGateway::class, function (Application $app): WhatsappGateway {
            /** @var SettingsService $settings */
            $settings = $app->make(SettingsService::class);

            $fakeFlag = $this->safe(fn () => $settings->get('whatsapp.fake', 'services.whatsapp.fake', '0'));
            $useFake = $app->environment('testing')
                || filter_var($fakeFlag, FILTER_VALIDATE_BOOLEAN);

            if ($useFake) {
                return new FakeWhatsappGateway();
            }

            return new MetaCloudGateway(
                accessToken: (string) $this->safe(fn () => $settings->get('whatsapp.access_token', 'services.whatsapp.access_token')),
                phoneNumberId: (string) $this->safe(fn () => $settings->get('whatsapp.phone_number_id', 'services.whatsapp.phone_number_id')),
                endpoint: $this->safe(fn () => $settings->get('whatsapp.endpoint', 'services.whatsapp.endpoint')),
                timeout: (int) config('services.whatsapp.timeout', 10),
            );
        });
    }

    /**
     * Bind the PushGateway contract. Same fake-vs-real switch pattern as
     * SMS and WhatsApp: fake in the `testing` environment, fake when the
     * `push.fake` setting is truthy (local dev + staging), Expo otherwise.
     * Access token is optional — Expo accepts anonymous /send calls for
     * the public ExponentPushToken scheme, but production installs that
     * opt into "Enhanced Security" pass a real token from Settings.
     */
    private function registerPushGateway(): void
    {
        $this->app->bind(PushGateway::class, function (Application $app): PushGateway {
            /** @var SettingsService $settings */
            $settings = $app->make(SettingsService::class);

            $fakeFlag = $this->safe(fn () => $settings->get('push.fake', 'services.push.fake', '0'));
            $useFake = $app->environment('testing')
                || filter_var($fakeFlag, FILTER_VALIDATE_BOOLEAN);

            if ($useFake) {
                return new FakePushGateway();
            }

            $accessToken = $this->safe(fn () => $settings->get('push.expo_access_token', 'services.push.access_token'));

            return new ExpoPushGateway($app->make(HttpFactory::class), $accessToken);
        });
    }

    /**
     * Rewrite mail config from admin-editable settings. Silent no-op when
     * the settings table doesn't exist yet (fresh install) or when the DB
     * itself is unavailable — the .env baseline still applies.
     */
    private function applyMailSettingsOverrides(): void
    {
        try {
            /** @var SettingsService $settings */
            $settings = $this->app->make(SettingsService::class);

            $resendKey = $settings->get('mail.resend_key', 'services.resend.key');
            $fromAddress = $settings->get('mail.from_address', 'mail.from.address');
            $fromName = $settings->get('mail.from_name', 'mail.from.name');

            if ($resendKey !== null && $resendKey !== '') {
                Config::set('services.resend.key', $resendKey);
                // Switch the default mailer to Resend the moment we have a
                // valid key, even if .env still says `log`. Removing the key
                // via the UI leaves the .env driver intact — safe fallback.
                Config::set('mail.default', 'resend');
            }
            if ($fromAddress !== null && $fromAddress !== '') {
                Config::set('mail.from.address', $fromAddress);
            }
            if ($fromName !== null && $fromName !== '') {
                Config::set('mail.from.name', $fromName);
            }
        } catch (Throwable) {
            // Boot must never fail because settings aren't queryable.
        }
    }

    /**
     * Run a settings lookup without ever leaking a DB exception to the
     * caller. Used inside container bindings and boot() where we cannot
     * assume the settings table is migrated yet.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function safe(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
