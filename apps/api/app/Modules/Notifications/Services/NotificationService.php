<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\LeaveRequest;
use App\Models\Request as RequestModel;
use App\Models\Task;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use Illuminate\Support\Collection;
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
 */
class NotificationService
{
    public function leaveDecided(LeaveRequest $leave): void
    {
        $recipient = $leave->employee?->user;
        if (! $recipient instanceof User) {
            return;
        }

        $approved = $leave->status->value === 'approved';

        Notification::send($recipient, new TaqatNotification(
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

        Notification::send($recipient, new TaqatNotification(
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

        Notification::send($approvers, new TaqatNotification(
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

    public function taskAssigned(Task $task): void
    {
        $recipient = $task->assignee?->user;
        if (! $recipient instanceof User) {
            return;
        }

        Notification::send($recipient, new TaqatNotification(
            title: 'تم إسناد مهمة إليك',
            body: $task->title,
            url: '/my-tasks',
            icon: 'clipboard-check',
            meta: ['task_id' => $task->id],
        ));
    }
}
