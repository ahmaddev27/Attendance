<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Listeners;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Events\InterviewScheduled;
use Illuminate\Support\Facades\Log;

/**
 * Internal nudge for the interview creator and the application's job
 * owner that a slot has been booked. Phase 2 Q7 locked: this is a
 * TAQAT-internal notification only — the candidate is never notified
 * from here.
 */
final class NotifyInterviewScheduled
{
    public function __construct(private readonly NotificationService $notifier) {}

    public function handle(InterviewScheduled $event): void
    {
        $interview = $event->interview;

        $recipients = [];

        $creator = $interview->createdBy;
        if ($creator instanceof User) {
            $recipients[$creator->id] = $creator;
        }

        $jobOwner = $interview->application?->jobRequirement?->owner;
        if ($jobOwner instanceof User) {
            $recipients[$jobOwner->id] = $jobOwner;
        }

        foreach ($recipients as $recipient) {
            try {
                $this->notifier->interviewScheduled($interview, $recipient);
            } catch (\Throwable $e) {
                Log::warning('interviewScheduled notifier failed', [
                    'interview_id' => $interview->id,
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
