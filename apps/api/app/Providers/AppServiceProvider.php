<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Gateways\FakeSmsGateway;
use App\Modules\Sms\Gateways\MtcSmsGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerSmsGateway();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Bind the SmsGateway contract. The fake driver is used
     *   - in the `testing` environment (so phpunit runs never touch MTC), or
     *   - when `services.mtc_sms.fake=true` (local dev + staging smoke tests
     *     without a live MTC account).
     * Everything else resolves the real MTC HTTP gateway.
     */
    private function registerSmsGateway(): void
    {
        $this->app->bind(SmsGateway::class, function (Application $app): SmsGateway {
            $useFake = $app->environment('testing')
                || (bool) config('services.mtc_sms.fake', false);

            if ($useFake) {
                return new FakeSmsGateway();
            }

            return new MtcSmsGateway(
                username: config('services.mtc_sms.username'),
                password: config('services.mtc_sms.password'),
                sender: (string) config('services.mtc_sms.sender', 'TAQAT'),
                endpoint: config('services.mtc_sms.endpoint'),
                timeout: (int) config('services.mtc_sms.timeout', 10),
            );
        });
    }
}
