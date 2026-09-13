<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Modules\Recruitment\Repositories\LeadActivityRepository;
use App\Modules\Recruitment\Repositories\LeadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * User-facing activity logging. System-generated entries (status_change,
 * owner_change, created/deleted markers) are still written through
 * LeadActivityRepository directly from LeadService — this class only
 * handles what a human deliberately logs, so it can update
 * lead.last_contact_at as a side effect.
 */
class LeadActivityService
{
    public function __construct(
        private readonly LeadActivityRepository $activities,
        private readonly LeadRepository $leads,
    ) {}

    public function paginateForLead(Lead $lead, int $perPage = 25): LengthAwarePaginator
    {
        return $this->activities->paginateForLead($lead, $perPage);
    }

    /**
     * @param  array<string, mixed>  $data  type/subject/body/occurred_at
     */
    public function create(Lead $lead, array $data, User $actor): LeadActivity
    {
        return DB::transaction(function () use ($lead, $data, $actor) {
            $activity = $this->activities->create([
                'lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => (string) $data['type'],
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Bump last_contact_at only for outbound-communication kinds
            // (call/meeting/email). A "note" is bookkeeping and does not
            // constitute a real touch.
            if (in_array($activity->type, ['call', 'meeting', 'email'], true)) {
                $occurred = $activity->occurred_at;

                if ($occurred !== null && ($lead->last_contact_at === null || $occurred->greaterThan($lead->last_contact_at))) {
                    $this->leads->update($lead, ['last_contact_at' => $occurred]);
                }
            }

            return $activity;
        });
    }
}
