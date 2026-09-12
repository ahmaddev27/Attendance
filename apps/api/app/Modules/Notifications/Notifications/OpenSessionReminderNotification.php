<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Notifications;

/**
 * "You forgot to scan out" reminder fired ~30 minutes before the
 * employee's shift-end for any attendance row that is still open
 * (check_in_at set, check_out_at null).
 *
 * The point of the reminder is to give the employee a chance to close
 * the session themselves before AutoCloseForgottenAttendance stamps
 * `check_out_at` at the schedule's declared shift-end — that auto-close
 * is a fallback, not the happy path.
 *
 * Extends TaqatNotification so the same channel matrix
 * (database + broadcast + optional mail/sms/whatsapp/push) applies
 * automatically. The only thing we override is the default payload —
 * push is always enabled here because the whole feature is about
 * reaching the employee on their phone in the moments before they
 * leave the office.
 *
 * NotifyOpenAttendanceSessions is the only caller; it also memoizes
 * per-attendance-row so a session isn't pinged twice within the
 * schedule's 15-minute cadence window.
 */
class OpenSessionReminderNotification extends TaqatNotification
{
    public function __construct(int $attendanceId)
    {
        parent::__construct(
            title: 'تذكير — لا تنسى تسجيل الانصراف',
            body: 'دوامك ينتهي خلال 30 دقيقة تقريباً. اضغط لتسجيل الانصراف من QR.',
            url: '/home',
            icon: 'clock',
            meta: ['attendance_id' => $attendanceId],
            // High-volume email would be inappropriate for a shift-end
            // ping — the durable DB row and the push are the two paths
            // that matter. SMS/WhatsApp stay off to avoid metered
            // spend on a low-severity nudge.
            suppressMail: true,
            sendPush: true,
        );
    }
}
