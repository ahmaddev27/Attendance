<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes the per-user notification opt-OUT matrix.
 *
 * Model is opt-OUT, not opt-IN: a brand-new user has zero rows and
 * receives every notification on every applicable channel — exactly as
 * {@see \App\Modules\Notifications\Notifications\TaqatNotification}
 * decided before this layer existed. A row is only written when the user
 * actively silences a (event, channel) pair.
 *
 * CHANNELS is the fixed catalog recognised by the preference matrix. We
 * intentionally keep the gate separate from TaqatNotification's own
 * via() checks — a channel can be "allowed" by the user here and still
 * be dropped by via() because, e.g., MTC isn't provisioned or the user
 * has no email on file.
 */
class NotificationPreferenceService
{
    /** @var list<string> */
    public const CHANNELS = ['database', 'broadcast', 'mail', 'sms', 'whatsapp', 'push'];

    /**
     * Returns true when the user has not disabled this (event, channel)
     * pair. Defaults to true for the common case where no row exists —
     * opt-OUT model.
     */
    public function isEnabled(User $user, string $eventKey, string $channel): bool
    {
        $row = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->first(['enabled']);

        return $row === null ? true : (bool) $row->enabled;
    }

    /**
     * Upsert one (user, event, channel) → enabled row. We write every
     * value — including enabled=true — so toggling a preference back ON
     * leaves an explicit row the user can audit, instead of silently
     * deleting it and letting the implicit default take over.
     */
    public function set(User $user, string $eventKey, string $channel, bool $enabled): NotificationPreference
    {
        /** @var NotificationPreference $row */
        $row = NotificationPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'event_key' => $eventKey,
                'channel' => $channel,
            ],
            [
                'enabled' => $enabled,
            ],
        );

        return $row;
    }

    /**
     * Bulk upsert — wraps every row in one transaction so the preference
     * page can PUT the whole matrix at once and either every row sticks
     * or none do.
     *
     * @param  array<int, array{event_key: string, channel: string, enabled: bool}>  $items
     */
    public function setMany(User $user, array $items): void
    {
        if ($items === []) {
            return;
        }

        DB::transaction(function () use ($user, $items): void {
            foreach ($items as $item) {
                $this->set(
                    $user,
                    (string) $item['event_key'],
                    (string) $item['channel'],
                    (bool) $item['enabled'],
                );
            }
        });
    }

    /**
     * Returns the whole stored matrix for a user keyed by
     * "{event_key}.{channel}" → bool. Only rows the user has touched are
     * present; the preferences page merges this against knownEventKeys()
     * so untouched cells default to true in the UI.
     *
     * @return array<string, bool>
     */
    public function allFor(User $user): array
    {
        /** @var array<string, bool> $map */
        $map = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get(['event_key', 'channel', 'enabled'])
            ->mapWithKeys(static fn (NotificationPreference $row): array => [
                $row->event_key.'.'.$row->channel => (bool) $row->enabled,
            ])
            ->all();

        return $map;
    }

    /**
     * Static catalog of every event the app currently emits through
     * NotificationService, in render order. Each entry carries:
     *   - label:            the Arabic label shown on the preferences page;
     *   - default_channels: the channels TaqatNotification is most likely to
     *                       pick for this event (used only to pre-mark the
     *                       cells on the UI — the gate still runs for every
     *                       channel the recipient actually has).
     *
     * Keep this in lockstep with NotificationService: each new domain event
     * should land here too.
     *
     * @return array<string, array{label: string, default_channels: list<string>}>
     */
    public function knownEventKeys(): array
    {
        return [
            'leave_decided' => [
                'label' => 'الرد على طلب الإجازة',
                'default_channels' => ['database', 'broadcast', 'mail'],
            ],
            'leave_submitted' => [
                'label' => 'طلب إجازة جديد بانتظار موافقتك',
                'default_channels' => ['database', 'broadcast', 'mail'],
            ],
            'request_decided' => [
                'label' => 'الرد على الطلبات العامة',
                'default_channels' => ['database', 'broadcast', 'mail'],
            ],
            'request_pending' => [
                'label' => 'طلب جديد بانتظار موافقتك',
                'default_channels' => ['database', 'broadcast', 'mail'],
            ],
            'task_assigned' => [
                'label' => 'إسناد مهمة جديدة',
                'default_channels' => ['database', 'broadcast', 'push'],
            ],
            'task_comment_mention' => [
                'label' => 'الإشارة إليك في تعليق مهمة',
                'default_channels' => ['database', 'broadcast', 'push'],
            ],
            'lead_converted' => [
                'label' => 'تحويل عميل محتمل إلى عميل فعلي',
                'default_channels' => ['database', 'broadcast'],
            ],
            'lead_created' => [
                'label' => 'إسناد عميل محتمل جديد',
                'default_channels' => ['database', 'broadcast'],
            ],
            'lead_stale' => [
                'label' => 'تأخر متابعة عميل محتمل',
                'default_channels' => ['database', 'broadcast'],
            ],
            'job_submitted' => [
                'label' => 'وظيفة جديدة في حملتك',
                'default_channels' => ['database', 'broadcast'],
            ],
            'job_stage_advanced' => [
                'label' => 'انتقال وظيفة إلى مرحلة جديدة',
                'default_channels' => ['database', 'broadcast'],
            ],
            'stage_sla_breached' => [
                'label' => 'تجاوز موعد مرحلة التوظيف',
                'default_channels' => ['database', 'broadcast', 'mail'],
            ],
        ];
    }

    /**
     * Reverse-map the dedup key emitted by NotificationService (e.g.
     * "leave-decided:42", "request-pending:17", "job-stage-advanced:9:3")
     * back to a canonical event_key the catalog recognises
     * ("leave_decided", "request_pending", "job_stage_advanced").
     *
     * Returns null when the prefix is unknown — callers treat null as
     * "no preference row to check" and fall through to the legacy
     * behaviour, which is the safe default when a brand-new event rolls
     * out before the catalog is updated.
     */
    public function resolveEventKey(string $dedupKey): ?string
    {
        $prefix = strstr($dedupKey, ':', true);
        if ($prefix === false || $prefix === '') {
            return null;
        }

        $candidate = str_replace('-', '_', $prefix);

        return array_key_exists($candidate, $this->knownEventKeys()) ? $candidate : null;
    }
}
