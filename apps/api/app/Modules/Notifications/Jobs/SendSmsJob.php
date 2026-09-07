<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Services\Sms\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued wrapper around SmsService::sendNow() — an SMS notification
 * channel dispatches this instead of calling the gateway inline so a
 * slow/unreachable MTC endpoint never blocks the HTTP request (or the
 * event listener) that triggered it. Retries 3 times before being
 * given up as failed; a permanently-failed send is still visible via
 * its `sms_logs` row (written by the last attempt) or, if every
 * attempt errored before reaching the gateway, via the failed_jobs
 * table.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $phone,
        private readonly string $message,
        private readonly ?Model $notifiable = null,
    ) {}

    public function handle(SmsService $sms): void
    {
        $sms->sendNow($this->phone, $this->message, $this->notifiable);
    }
}
