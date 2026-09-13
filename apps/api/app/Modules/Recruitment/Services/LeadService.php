<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Lead;
use App\Models\User;
use App\Modules\Recruitment\Events\LeadCreated;
use App\Modules\Recruitment\Repositories\LeadActivityRepository;
use App\Modules\Recruitment\Repositories\LeadRepository;
use App\Shared\Enums\LeadStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates every Lead write. Reads go through the repository directly
 * — the service only fronts writes so it can wrap them in a transaction,
 * mint the lead_number, log activity, and fire events after commit.
 */
class LeadService
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly LeadActivityRepository $activities,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->leads->paginate($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<string, Collection<int, Lead>>
     */
    public function kanban(array $filters = []): Collection
    {
        return $this->leads->groupByStatus([...$filters, 'active_only' => true]);
    }

    public function find(int $id): Lead
    {
        return $this->leads->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Lead
    {
        // Owner defaults to the acting user — a Sales rep creating a
        // lead becomes its owner unless they explicitly reassign it.
        $data['owner_id'] = $data['owner_id'] ?? $actor->id;

        $force = (bool) ($data['force'] ?? false);
        unset($data['force']);

        // Soft dedup: warn the caller when a suspiciously similar lead
        // already exists. UX flow: FE surfaces the duplicate, offers to
        // reuse or force-create; force=true bypasses this branch.
        if (! $force && $this->leads->duplicateExists(
            (string) $data['company_name'],
            $data['country'] ?? null,
        )) {
            throw ValidationException::withMessages([
                'company_name' => 'يوجد عميل محتمل بنفس الاسم والدولة. مرّر force=true للحفظ بدون دمج.',
            ]);
        }

        $lead = DB::transaction(function () use ($data, $actor) {
            $data['lead_number'] = $this->numbers->nextLeadNumber();

            $created = $this->leads->create($data);

            // System-generated timeline entry so the Lead detail's
            // activity feed carries "created by X" even when no
            // manual activity was logged.
            $this->activities->create([
                'lead_id' => $created->id,
                'user_id' => $actor->id,
                'type' => 'note',
                'subject' => 'تم إنشاء العميل المحتمل',
                'body' => null,
                'occurred_at' => now(),
                'metadata' => ['system' => true, 'action' => 'created'],
            ]);

            return $created;
        });

        // Post-commit fan-out — a broadcast error here can't roll back
        // the insert (mirrors LeaveRequestService::submit's pattern).
        LeadCreated::dispatch($lead, $actor);

        return $lead;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lead $lead, array $data, User $actor): Lead
    {
        $previousStatus = $lead->status;
        $previousOwnerId = $lead->owner_id;

        $updated = DB::transaction(function () use ($lead, $data, $actor, $previousStatus, $previousOwnerId) {
            // Reject writes to converted/lost leads — the whole point of
            // those states is to freeze the record.
            if (in_array($previousStatus, [LeadStatus::Converted, LeadStatus::Lost], true)) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تعديل عميل محتمل بعد تحويله أو خسارته.',
                ]);
            }

            // Auto-stamp lost_at when transitioning INTO lost.
            $incomingStatus = isset($data['status']) ? LeadStatus::from((string) $data['status']) : null;

            if ($incomingStatus === LeadStatus::Lost) {
                $data['lost_at'] = $data['lost_at'] ?? now();
            }

            // Converted transitions must go through
            // LeadConversionService — direct status=converted flips
            // would leave the FK trail (converted_client_id) unset.
            if ($incomingStatus === LeadStatus::Converted) {
                throw ValidationException::withMessages([
                    'status' => 'استخدم مسار التحويل POST /leads/{id}/convert بدل تعيين الحالة مباشرة.',
                ]);
            }

            $fresh = $this->leads->update($lead, $data);

            $this->logSystemChanges($fresh, $actor, $previousStatus, $previousOwnerId);

            return $fresh;
        });

        return $updated;
    }

    public function delete(Lead $lead, User $actor): void
    {
        DB::transaction(function () use ($lead, $actor) {
            $this->activities->create([
                'lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => 'note',
                'subject' => 'تم حذف العميل المحتمل',
                'occurred_at' => now(),
                'metadata' => ['system' => true, 'action' => 'deleted'],
            ]);

            $lead->delete();
        });
    }

    private function logSystemChanges(Lead $lead, User $actor, ?LeadStatus $previousStatus, ?int $previousOwnerId): void
    {
        if ($previousStatus !== null && $lead->status !== $previousStatus) {
            $this->activities->create([
                'lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => 'status_change',
                'subject' => sprintf('%s → %s', $previousStatus->value, $lead->status?->value),
                'occurred_at' => now(),
                'metadata' => [
                    'system' => true,
                    'from_status' => $previousStatus->value,
                    'to_status' => $lead->status?->value,
                ],
            ]);
        }

        if ($previousOwnerId !== null && $lead->owner_id !== $previousOwnerId) {
            $this->activities->create([
                'lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => 'note',
                'subject' => 'تم تغيير المسؤول',
                'occurred_at' => now(),
                'metadata' => [
                    'system' => true,
                    'action' => 'owner_change',
                    'from_owner_id' => $previousOwnerId,
                    'to_owner_id' => $lead->owner_id,
                ],
            ]);
        }
    }
}
