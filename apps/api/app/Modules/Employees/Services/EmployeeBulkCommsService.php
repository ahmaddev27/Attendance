<?php

declare(strict_types=1);

namespace App\Modules\Employees\Services;

use App\Models\Employee;
use App\Models\User;
use App\Modules\Notifications\Notifications\TaqatNotification;
use App\Modules\Sms\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Owner-requested bulk email + bulk SMS fan-out from the Employees admin
 * page. The admin selects a set of employees, writes one message, and we
 * resolve each employee to the right address (user->email for mail, phone
 * for SMS), skip those without one, and queue a per-recipient send.
 *
 * Two "skip reasons" surface in the response envelope so the admin sees up
 * front how many of their selected employees won't actually receive the
 * message — bulk SMS is metered, and silently dropping half the roster
 * would be an easy foot-gun.
 *
 * Each individual send is enqueued (via SendSmsJob and the queue
 * notification channel) so the admin's HTTP request returns in constant
 * time regardless of recipient count.
 */
class EmployeeBulkCommsService
{
    public function __construct(
        private readonly SmsService $sms,
    ) {}

    /**
     * Send one email to each of the given employees. Returns {queued,
     * skipped_no_email} so the FE can show both numbers in the toast.
     *
     * @param  array<int, int>  $employeeIds
     * @return array{queued: int, skipped_no_email: int}
     */
    public function sendEmail(array $employeeIds, string $subject, string $body): array
    {
        $employees = $this->resolveEmployees($employeeIds);

        $queued = 0;
        $skippedNoEmail = 0;

        foreach ($employees as $employee) {
            $recipient = $this->resolveMailRecipient($employee);

            if ($recipient === null) {
                $skippedNoEmail++;

                continue;
            }

            // Reuse the generic TaqatNotification shape for consistency with
            // the per-event notifications already in the system — same mail
            // template, same deep-link machinery, same logging. The mail-only
            // flag keeps the broadcast and SMS channels off so this doesn't
            // double up with the regular inbox events.
            $notification = new TaqatNotification(
                title: $subject,
                body: $body,
                // Email-only broadcast: suppress the real-time toast so this
                // doesn't show up as a mysterious notification on the
                // recipient's screen. The DB row is still kept as a durable
                // record that the message was sent.
                suppressBroadcast: true,
                suppressMail: false,
            );

            try {
                Notification::send($recipient, $notification);
                $queued++;
            } catch (Throwable $e) {
                // A single failed enqueue (e.g. the mailer transport blew up
                // halfway through the batch) must not abort the loop — the
                // other recipients still deserve the message.
                Log::warning('[EmployeeBulkCommsService::sendEmail] enqueue failed', [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);
                $skippedNoEmail++;
            }
        }

        return [
            'queued' => $queued,
            'skipped_no_email' => $skippedNoEmail,
        ];
    }

    /**
     * Send one SMS to each of the given employees. Returns {queued,
     * skipped_no_phone} so the FE can show both numbers in the toast.
     *
     * @param  array<int, int>  $employeeIds
     * @return array{queued: int, skipped_no_phone: int}
     */
    public function sendSms(array $employeeIds, string $body): array
    {
        $employees = $this->resolveEmployees($employeeIds);

        $queued = 0;
        $skippedNoPhone = 0;

        foreach ($employees as $employee) {
            $phone = is_string($employee->phone) ? trim($employee->phone) : '';

            if ($phone === '') {
                $skippedNoPhone++;

                continue;
            }

            try {
                $this->sms->send(to: $phone, body: $body);
                $queued++;
            } catch (Throwable $e) {
                Log::warning('[EmployeeBulkCommsService::sendSms] enqueue failed', [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);
                $skippedNoPhone++;
            }
        }

        return [
            'queued' => $queued,
            'skipped_no_phone' => $skippedNoPhone,
        ];
    }

    /**
     * Resolve the employee set once with user+employee eager-loaded. We DO
     * NOT skip system accounts here — the admin may legitimately want to
     * include a system-linked record in the batch — but we do dedupe and
     * drop ids that no longer exist.
     *
     * @param  array<int, int>  $employeeIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Employee>
     */
    private function resolveEmployees(array $employeeIds): \Illuminate\Database\Eloquent\Collection
    {
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));

        return Employee::query()
            ->with('user:id,email')
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * The Mail channel reads $notifiable->email directly, so a User (the
     * employee's login account) is the correct recipient when one exists.
     * Fall back to the Employee itself when no User is linked yet — the
     * notifiable then needs its own email, which Employee has.
     *
     * Returns null when neither surface has an email we can send to.
     */
    private function resolveMailRecipient(Employee $employee): object|null
    {
        $userEmail = $employee->user?->email;
        if (is_string($userEmail) && trim($userEmail) !== '') {
            return $employee->user;
        }

        if (is_string($employee->email) && trim($employee->email) !== '') {
            // Minimal AnonymousNotifiable-style ad-hoc recipient. Laravel's
            // Notification::route('mail', ...) ships a plain address through
            // the Mail channel without needing a persisted model.
            return Notification::route('mail', $employee->email);
        }

        return null;
    }
}
