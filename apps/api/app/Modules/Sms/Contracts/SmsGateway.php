<?php

declare(strict_types=1);

namespace App\Modules\Sms\Contracts;

/**
 * Provider-agnostic contract for sending a single SMS.
 *
 * Implementations MUST NOT throw for transport-level failures (bad DNS,
 * 5xx from carrier, malformed provider response) — every failure is
 * converted into an `SmsResult::failure()` so the caller (SmsService,
 * SendSmsJob) can log it and decide whether to retry. Throwing would
 * leak carrier internals into the queue's exception path and defeat the
 * retry/backoff strategy configured on SendSmsJob.
 *
 * The concrete binding is resolved from the container in
 * AppServiceProvider — MtcSmsGateway in production, FakeSmsGateway when
 * the app is running in the `testing` environment or when
 * `services.mtc_sms.fake` is truthy.
 */
interface SmsGateway
{
    /**
     * @param  string  $to    E.164-normalised recipient MSISDN (e.g. "9627XXXXXXXX").
     * @param  string  $body  Plain text; callers are responsible for keeping
     *                        the length within a single SMS segment (see
     *                        SmsChannel which truncates to 160 chars).
     */
    public function send(string $to, string $body): SmsResult;
}
