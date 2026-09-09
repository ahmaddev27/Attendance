<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

use App\Modules\Push\Notifications\Channels\PushChannel;
use App\Modules\Sms\Notifications\Channels\SmsChannel;
use App\Modules\Whatsapp\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Single generic in-app notification.
 *
 * Every domain event that wants to notify a user (leave approved, task
 * assigned, request returned, ...) constructs one of these instead of
 * introducing a per-event notification class — the payload is a fixed
 * shape ({title, body, url, icon, meta}) so both the API list endpoint
 * and the frontend bell can render any notification without a switch on
 * `type`. See NotificationService for the callers.
 *
 * Delivery paths:
 *   - database  : durable, powers the /me/notifications list + unread count
 *   - broadcast : real-time push via Reverb; the bell subscribes on connect
 *                 and invalidates its react-query cache so a fresh
 *                 notification appears without waiting for the 30s poll.
 *   - mail      : added automatically when the app is configured with a
 *                 real mail transport (i.e. not `log` / `array`) AND the
 *                 recipient has an email address on file. Delivered via
 *                 Resend in production (see config/mail.php).
 *   - sms       : OPT-IN per notification via the $sendSms flag. Added
 *                 only when MTC credentials are configured AND the
 *                 recipient's linked Employee has a phone. Kept opt-in
 *                 because SMS is metered and most in-app notifications
 *                 don't warrant a text.
 *   - whatsapp  : OPT-IN per notification via the $sendWhatsapp flag.
 *                 Added only when Meta Cloud API credentials are
 *                 configured (or the fake driver is on for local /
 *                 staging smoke tests) AND the recipient's linked
 *                 Employee has a phone. Same rationale as SMS:
 *                 templates cost money and free-form messages only work
 *                 inside the 24h customer-service window, so every send
 *                 is deliberate.
 */
class TaqatNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Route this notification's channel sends onto the queue worker so
    // the request thread doesn't block on Mail/Broadcast/SMS/WhatsApp/Push
    // HTTP calls (each up to 10s). Scale audit (2026-09-09) traced a
    // 5-worker php-fpm cap starving at ~200 concurrent users because
    // every mutation was firing sync notification I/O inside the request.
    public $afterCommit = true;

    /**
     * @param  array<string, mixed>  $meta  Optional structured payload
     *                                       (leave_request_id, task_id, ...)
     * @param  bool  $suppressBroadcast  Set by NotificationService when the
     *                                    same event was already broadcast to
     *                                    the same recipient within the dedup
     *                                    window — the DB row is still written
     *                                    (durable inbox) but Reverb is skipped
     *                                    to avoid toast/counter double-fires.
     * @param  bool  $suppressMail  Opt-out for high-volume events (e.g. every
     *                               approver on a step) that would otherwise
     *                               flood inboxes. Database + broadcast still
     *                               fire as usual.
     * @param  bool  $sendSms  Opt-IN to also send this notification as an SMS
     *                          via MTC. Defaults false because SMS costs money;
     *                          only fires when MTC credentials are configured
     *                          and the recipient's employee has a phone.
     * @param  bool  $sendWhatsapp  Opt-IN to also send this notification over
     *                               WhatsApp via Meta's Cloud API. Defaults
     *                               false; only fires when WhatsApp
     *                               credentials are configured (or the fake
     *                               driver is on) AND the recipient's employee
     *                               has a phone.
     * @param  bool  $sendPush  Opt-IN to also send this notification as a
     *                           mobile push via Expo. Defaults false; only
     *                           fires when the recipient has at least one
     *                           registered push_tokens row (the mobile app
     *                           writes one on login and clears it on logout).
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?string $url = null,
        public readonly ?string $icon = null,
        public readonly array $meta = [],
        public readonly bool $suppressBroadcast = false,
        public readonly bool $suppressMail = false,
        public readonly bool $sendSms = false,
        public readonly bool $sendWhatsapp = false,
        public readonly bool $sendPush = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['database'];

        // Broadcast is added when Reverb is configured AND the caller
        // didn't ask us to suppress it (see $suppressBroadcast).
        if (! $this->suppressBroadcast && config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }

        // Mail is added only when a real transport is configured (Resend/SMTP
        // etc — never for `log` or `array`, which are dev/test-only) AND the
        // recipient actually has an email address on file. suppressMail lets
        // high-volume callers opt out without touching the transport config.
        if (! $this->suppressMail && $this->hasRealMailTransport() && ! empty($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        // SMS is opt-in per notification and only fires when MTC is
        // provisioned (username set OR fake driver enabled for smoke
        // tests) AND the recipient's employee has a phone we can dial.
        if ($this->sendSms && $this->smsIsSendable($notifiable)) {
            $channels[] = SmsChannel::class;
        }

        // WhatsApp is opt-in per notification and only fires when Meta
        // Cloud API is provisioned (access_token set OR fake driver
        // enabled for smoke tests) AND the recipient's employee has a
        // phone we can dial.
        if ($this->sendWhatsapp && $this->whatsappIsSendable($notifiable)) {
            $channels[] = WhatsappChannel::class;
        }

        // Mobile push is opt-in per notification. Unlike SMS/WhatsApp we
        // don't need credentials to be present (Expo's public scheme
        // requires none) — only that the recipient has at least one
        // registered push_tokens row. If they don't, the channel is a
        // no-op anyway; we skip it here to avoid an empty DB query.
        if ($this->sendPush && ($notifiable->pushTokens?->isNotEmpty() ?? false)) {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    /**
     * True when the recipient can receive an SMS via MTC — i.e. the
     * gateway is provisioned (real credentials OR the fake driver is
     * explicitly enabled for local/staging smoke tests) AND we can
     * resolve a phone from the notifiable's linked Employee. Guards
     * every hop because either half can legitimately be missing (an
     * admin User without an Employee, or an Employee without a phone).
     */
    private function smsIsSendable(mixed $notifiable): bool
    {
        $hasCredentials = ! empty(config('services.mtc_sms.username'))
            || (bool) config('services.mtc_sms.fake', false);

        if (! $hasCredentials) {
            return false;
        }

        $phone = $notifiable->employee?->phone ?? null;

        return is_string($phone) && $phone !== '';
    }

    /**
     * True when the recipient can receive a WhatsApp message via Meta's
     * Cloud API — i.e. the gateway is provisioned (access_token AND
     * phone_number_id set OR the fake driver is explicitly enabled for
     * local/staging smoke tests) AND we can resolve a phone from the
     * notifiable's linked Employee. Guards every hop because either
     * half can legitimately be missing (an admin User without an
     * Employee, or an Employee without a phone).
     */
    private function whatsappIsSendable(mixed $notifiable): bool
    {
        $hasCredentials = (! empty(config('services.whatsapp.access_token'))
                && ! empty(config('services.whatsapp.phone_number_id')))
            || (bool) config('services.whatsapp.fake', false);

        if (! $hasCredentials) {
            return false;
        }

        $phone = $notifiable->employee?->phone ?? null;

        return is_string($phone) && $phone !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
            'meta' => $this->meta,
        ];
    }

    /**
     * Payload delivered over Reverb. Kept identical to toArray() so the
     * bell can render the same DTO either way — it uses whichever it
     * receives first (broadcast for realtime, database on refresh).
     */
    public function toBroadcast(mixed $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Build the transactional email. Uses the default Notification blade
     * (which renders RTL naturally with our Arabic copy) — a bespoke
     * template can be introduced later without touching callers.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject($this->title)
            ->greeting('مرحبًا ' . ($notifiable->name ?? ''))
            ->line($this->body ?? '');

        if ($this->url !== null && $this->url !== '') {
            // Deep-link back into the frontend SPA. APP_URL is expected to
            // point at the FE origin (config/app.php reads it from env).
            $mail->action('عرض التفاصيل', rtrim((string) config('app.url'), '/') . $this->url);
        }

        return $mail
            ->line('شكرًا لاستخدامك منصة TAQAT.')
            ->salutation('فريق TAQAT');
    }

    /**
     * Payload delivered over the SmsChannel. Kept short — a standard
     * GSM-7 SMS is 160 characters; anything longer either splits (extra
     * cost) or is silently truncated by the carrier. We pre-truncate
     * with an ellipsis so the recipient sees an intentional cut-off.
     *
     * Title + body are concatenated with " - " so the SMS reads as one
     * self-contained sentence; when body is empty we send the title alone.
     */
    public function toSms(mixed $notifiable): string
    {
        $composed = $this->body !== null && $this->body !== ''
            ? $this->title.' - '.$this->body
            : $this->title;

        return Str::limit($composed, 157, '...');
    }

    /**
     * Payload delivered over the PushChannel (Expo). `data` carries the
     * same structured meta as the DB row so the mobile app can deep-link
     * into the right screen when the recipient taps the notification —
     * we deliberately keep `data` a plain scalar map so Expo's JSON
     * round-trip doesn't drop anything.
     *
     * @return array{title: string, body: ?string, data: array<string, mixed>}
     */
    public function toPush(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'data' => array_filter([
                'url' => $this->url,
                'icon' => $this->icon,
                'meta' => $this->meta,
            ], static fn ($v) => $v !== null),
        ];
    }

    /**
     * Payload delivered over the WhatsappChannel. WhatsApp accepts up to
     * 4096 chars per free-form message, so unlike toSms() we don't
     * truncate — the full title + body is sent verbatim (blank body
     * degrades to title-only). We separate with a newline so the message
     * reads as a subject + description on the recipient's handset.
     */
    public function toWhatsapp(mixed $notifiable): string
    {
        $composed = $this->body !== null && $this->body !== ''
            ? $this->title."\n".$this->body
            : $this->title;

        return trim($composed);
    }

    /**
     * True when Laravel's default mailer will actually try to send — i.e.
     * anything other than the dev-only `log` and test-only `array` drivers.
     * Keeps us from wiring 'mail' into via() during phpunit runs (which
     * default to `array`) or local `MAIL_MAILER=log` setups.
     */
    private function hasRealMailTransport(): bool
    {
        $driver = (string) config('mail.default');

        return $driver !== '' && $driver !== 'log' && $driver !== 'array';
    }
}
