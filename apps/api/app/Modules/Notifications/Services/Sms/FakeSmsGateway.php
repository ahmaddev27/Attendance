<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services\Sms;

use App\Shared\DataObjects\SmsResult;

/**
 * In-memory test double for SmsGatewayInterface — never makes a real
 * HTTP call. Bound in place of MtcSmsGateway whenever the app runs
 * under the `testing` environment (see
 * NotificationsServiceProvider::register()), so every notification and
 * listener test in this suite is safe from flaky/slow network calls to
 * the real MTC endpoint by default, without every test needing to
 * remember to fake it explicitly.
 *
 * Tests that specifically exercise SmsService/notification behavior can
 * still bind a fresh instance themselves (`$this->app->instance(...)`)
 * to control success/failure and inspect what was "sent".
 */
class FakeSmsGateway implements SmsGatewayInterface
{
    /**
     * @var list<array{to: string, message: string}>
     */
    public array $sent = [];

    private bool $shouldFail = false;

    private string $failureCode = 'fake_failure';

    private string $failureResponse = 'fake failure response';

    public function send(string $to, string $message): SmsResult
    {
        $this->sent[] = ['to' => $to, 'message' => $message];

        if ($this->shouldFail) {
            return SmsResult::failure($this->failureCode, $this->failureResponse);
        }

        return SmsResult::success('0@OK');
    }

    /**
     * Makes every subsequent send() report a failure, until reset.
     */
    public function failNext(string $code = 'fake_failure', string $response = 'fake failure response'): static
    {
        $this->shouldFail = true;
        $this->failureCode = $code;
        $this->failureResponse = $response;

        return $this;
    }

    public function reset(): static
    {
        $this->sent = [];
        $this->shouldFail = false;

        return $this;
    }
}
