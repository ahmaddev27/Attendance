<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Listeners;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Events\LeadCreated;
use Illuminate\Support\Facades\Log;

/**
 * In-app nudge for a lead's owner when somebody else hands them the
 * lead. Self-assignment (the default when a rep creates their own
 * lead) is silent — telling people about their own action is noise.
 */
final class NotifyLeadOwnerOfNewLead
{
    public function __construct(private readonly NotificationService $notifier) {}

    public function handle(LeadCreated $event): void
    {
        $lead = $event->lead;
        $owner = $lead->owner;

        if (! $owner instanceof User) {
            return;
        }

        if ($event->actor instanceof User && $event->actor->id === $owner->id) {
            return;
        }

        // Notifier failures must never surface as a failed create — the
        // row is already committed by the time this listener runs.
        try {
            $this->notifier->leadCreated($lead, $owner);
        } catch (\Throwable $e) {
            Log::warning('leadCreated notifier failed', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
