<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Contracts;

/**
 * Provider-agnostic contract for sending a single WhatsApp message.
 *
 * Implementations MUST NOT throw for transport-level failures (bad DNS,
 * 5xx from Meta, malformed provider response) — every failure is
 * converted into a `WhatsappResult::failure()` so the caller
 * (WhatsappService, SendWhatsappJob) can log it and decide whether to
 * retry. Throwing would leak provider internals into the queue's
 * exception path and defeat the retry/backoff strategy configured on
 * SendWhatsappJob.
 *
 * The concrete binding is resolved from the container in
 * AppServiceProvider — MetaCloudGateway in production, FakeWhatsappGateway
 * when the app is running in the `testing` environment or when
 * `services.whatsapp.fake` is truthy.
 */
interface WhatsappGateway
{
    /**
     * @param  string  $to    Recipient MSISDN in E.164 (e.g. "+9627XXXXXXXX").
     *                        Meta accepts the value with or without the leading
     *                        `+` — callers should send the fullest form they
     *                        have; the gateway normalises defensively.
     * @param  string  $body  Free-form text (Meta accepts up to 4096 chars).
     *                        Templates + rich messages are a future concern; a
     *                        24h session window applies to free-form messages
     *                        (see the module README for details).
     */
    public function send(string $to, string $body): WhatsappResult;
}
