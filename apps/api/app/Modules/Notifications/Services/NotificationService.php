<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Thin façade over the Laravel notification pipeline. Every caller in the
 * app funnels through one of these helpers so notification wording and
 * deep-link URLs live in one place — if we ever move to a different
 * channel (broadcast, mail, mtc-sms) or change how deep links are built,
 * only this class needs to change.
 *
 * Silently no-ops when the resolved recipient can't be found (e.g. a
 * leave belongs to a soft-deleted employee whose linked user is gone) —
 * we don't want a notification error to break the underlying action.
 *
 * Dedup: when the same event key fires for the same recipient within
 * DEDUP_WINDOW seconds, the DB row is still written (durable inbox) but
 * the Reverb broadcast is suppressed so the frontend bell/toast doesn't
 * double-fire. See dispatch().
 */
class NotificationService
{
    /**
     * Suppress-broadcast window in seconds. Long enough to absorb a
     * double-click or a rapid re-approve, short enough that a deliberate
     * second event ~a minute later still reaches the toast.
     */
    private const DEDUP_WINDOW = 30;

    public function leaveDecided(LeaveRequest $leave): void
    {
        $recipient = $leave->employee?->user;
        if (! $recipient instanceof User) {
            return;
        }

        $approved = $leave->status->value === 'approved';

        $this->dispatch($recipient, "leave-decided:{$leave->id}", new TaqatNotification(
            title: $approved
                ? 'تمت الموافقة على طلب إجازتك'
                : 'تم رفض طلب إجازتك',
            body: sprintf(
                '%s — من %s إلى %s',
                $leave->leaveType?->name ?? 'إجازة',
                $leave->start_date?->format('Y-m-d') ?? '',
                $leave->end_date?->format('Y-m-d') ?? '',
            ),
            url: '/my-leaves',
            icon: $approved ? 'check-circle' : 'x-circle',
            meta: ['leave_request_id' => $leave->id],
        ));
    }

    /**
     * Notify each HR user with the `approve-leaves` permission that a
     * new leave request has been submitted. Mirrors
     * requestPendingApproval()'s fan-out shape: eager-loads the two
     * relations TaqatNotification::via() reads to avoid an N+1 across
     * approvers, and gives each recipient their own dedup key so a
     * quick re-submission still surfaces per person.
     *
     * @param  Collection<int, User>  $approvers
     */
    public function leaveSubmitted(LeaveRequest $leave, Collection $approvers): void
    {
        if ($approvers->isEmpty()) {
            return;
        }

        $approvers->load(['pushTokens:id,user_id', 'employee:id,phone']);

        $employeeName = $leave->employee?->full_name ?? 'موظف';
        $typeName = $leave->leaveType?->name ?? 'إجازة';
        $start = $leave->start_date?->format('Y-m-d') ?? '';
        $end = $leave->end_date?->format('Y-m-d') ?? '';

        foreach ($approvers as $approver) {
            $this->dispatch($approver, "leave-submitted:{$leave->id}", new TaqatNotification(
                title: 'طلب إجازة جديد بانتظار موافقتك',
                body: sprintf('%s — %s (%s → %s)', $employeeName, $typeName, $start, $end),
                url: '/leaves',
                icon: 'inbox',
                meta: ['leave_request_id' => $leave->id],
            ));
        }
    }

    public function requestDecided(RequestModel $request): void
    {
        $recipient = $request->employee?->user;
        if (! $recipient instanceof User) {
            return;
        }

        $status = $request->status->value;
        $title = match ($status) {
            'approved' => 'تمت الموافقة على طلبك',
            'rejected' => 'تم رفض طلبك',
            'returned' => 'أُعيد طلبك لك للتعديل',
            default => "تحديث على طلبك ({$status})",
        };

        $this->dispatch($recipient, "request-decided:{$request->id}:{$status}", new TaqatNotification(
            title: $title,
            body: sprintf(
                '%s — %s',
                $request->requestType?->name ?? 'طلب',
                $request->request_number,
            ),
            url: '/my-requests',
            icon: $status === 'approved' ? 'check-circle' : ($status === 'rejected' ? 'x-circle' : 'refresh-cw'),
            meta: ['request_id' => $request->id, 'request_number' => $request->request_number],
        ));
    }

    /**
     * Notify each approver on the newly-active step that a request needs
     * their attention. Approvers is a Collection<User>.
     *
     * @param  Collection<int, User>  $approvers
     */
    public function requestPendingApproval(RequestModel $request, Collection $approvers): void
    {
        if ($approvers->isEmpty()) {
            return;
        }

        // Prime the two relations TaqatNotification::via() reads on each
        // recipient (pushTokens for the push-channel gate, employee for
        // the sms/whatsapp phone lookup). Without this, every approver in
        // the fanout triggers 2 lazy loads at notification-send time — a
        // classic N+1 that scales with approver-count per step.
        $approvers->load(['pushTokens:id,user_id', 'employee:id,phone']);

        // Each approver gets their own dedup key so the loop still catches
        // "same request re-forwarded to me twice in 30s" per-approver.
        foreach ($approvers as $approver) {
            $this->dispatch($approver, "request-pending:{$request->id}", new TaqatNotification(
                title: 'طلب جديد بانتظار موافقتك',
                body: sprintf(
                    '%s — %s من %s',
                    $request->requestType?->name ?? 'طلب',
                    $request->request_number,
                    $request->employee?->full_name ?? 'موظف',
                ),
                url: '/approvals',
                icon: 'inbox',
                meta: ['request_id' => $request->id],
            ));
        }
    }

    public function taskAssigned(Task $task): void
    {
        $recipient = $task->assignee?->user;
        if (! $recipient instanceof User) {
            return;
        }

        $this->dispatch($recipient, "task-assigned:{$task->id}", new TaqatNotification(
            title: 'تم إسناد مهمة إليك',
            body: $task->title,
            url: "/my-tasks/{$task->id}",
            icon: 'clipboard-check',
            meta: ['task_id' => $task->id],
        ));
    }

    /**
     * Send a notification with per-recipient dedup on the broadcast channel.
     *
     * Every path in this service goes through here. If the same $eventKey
     * fired for the same user in the last DEDUP_WINDOW seconds, we clone
     * the notification with suppressBroadcast=true — the durable DB row
     * still lands (so the bell counter and history page catch up on the
     * next poll/open), but Reverb doesn't fan out a second toast/counter
     * update.
     */
    private function dispatch(User $recipient, string $eventKey, TaqatNotification $notification): void
    {
        $cacheKey = "notif-dedup:{$recipient->id}:{$eventKey}";

        $isDuplicate = ! Cache::add($cacheKey, 1, self::DEDUP_WINDOW);

        if ($isDuplicate) {
            // Preserve every channel flag the caller set (sendSms /
            // sendWhatsapp / sendPush / suppressMail) — the earlier
            // partial clone was dropping them silently, which meant a
            // deduped "leave decided" second event downgraded to
            // database-only and skipped the SMS the caller expected.
            $notification = new TaqatNotification(
                title: $notification->title,
                body: $notification->body,
                url: $notification->url,
                icon: $notification->icon,
                meta: $notification->meta,
                suppressBroadcast: true,
                suppressMail: $notification->suppressMail,
                sendSms: $notification->sendSms,
                sendWhatsapp: $notification->sendWhatsapp,
                sendPush: $notification->sendPush,
            );
        }

        Notification::send($recipient, $notification);
    }
}
