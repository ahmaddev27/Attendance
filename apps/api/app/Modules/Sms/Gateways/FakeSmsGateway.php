<?php

declare(strict_types=1);

namespace App\Modules\Sms\Gateways;

use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use App\Shared\Support\RedactsSensitiveText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * No-op SMS gateway that writes to the Laravel log instead of hitting a
 * real carrier. Bound in AppServiceProvider whenever
 *   - the application is running in the `testing` environment, or
 *   - `config('services.mtc_sms.fake')` is truthy (local dev without a
 *     live MTC account, staging smoke tests, ...).
 *
 * Always returns success with a synthesised `provider_message_id` so the
 * downstream sms_logs row and any test assertions see a stable shape.
 */
final class FakeSmsGateway implements SmsGateway
{
    use RedactsSensitiveText;

    public function send(string $to, string $body): SmsResult
    {
        $fakeId = 'fake-'.Str::uuid()->toString();
        $safeBody = $this->redactBody($body);

        Log::info('[FakeSmsGateway] SMS delivered (no carrier hit)', [
            'to' => $to,
            // Log the redacted body — a fake gateway is what dev/test
            // environments use, and the application log is not a place
            // for plaintext welcome-SMS passwords.
            'body' => $safeBody,
            'provider_message_id' => $fakeId,
        ]);

        return SmsResult::success($fakeId, [
            'driver' => 'fake',
            'to' => $to,
            'body' => $safeBody,
        ]);
    }
}
