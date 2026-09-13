<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Client;
use App\Models\Lead;
use App\Models\RecruitmentCase;
use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Recruitment\Events\LeadConverted;
use App\Modules\Recruitment\Repositories\ClientRepository;
use App\Modules\Recruitment\Repositories\LeadActivityRepository;
use App\Modules\Recruitment\Repositories\LeadRepository;
use App\Shared\Enums\LeadStatus;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The single composer for Lead → Client + Case + Jobs. Kept outside
 * LeadService because it reaches into ClientRepository, CaseService,
 * JobRequirementService, and LeadActivity — a fat method belongs in
 * its own class rather than as a private method that pulls in half of
 * the module's dependencies.
 *
 * Guarantees:
 *   - the whole conversion runs in one DB transaction (all or nothing)
 *   - the Lead is only marked Converted after every downstream row is
 *     persisted, so a rollback leaves the Lead in its pre-conversion
 *     state
 *   - events fire ONLY after commit (LeadConverted → notifications;
 *     JobRequirementStageAdvanced already fires from JobService::create
 *     for each new job — that seeds the "publish" tasks automatically)
 */
class LeadConversionService
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly ClientRepository $clients,
        private readonly ClientContactService $contacts,
        private readonly RecruitmentCaseService $cases,
        private readonly JobRequirementService $jobs,
        private readonly LeadActivityRepository $activities,
        private readonly NotificationService $notifier,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  ConvertLeadRequest-validated shape
     * @return array{lead: Lead, client: Client, case: RecruitmentCase, jobs: Collection<int, \App\Models\JobRequirement>}
     */
    public function convert(Lead $lead, array $payload, User $actor): array
    {
        if ($lead->status === LeadStatus::Converted) {
            throw ValidationException::withMessages([
                'status' => 'العميل المحتمل محوَّل مسبقاً.',
            ]);
        }

        if ($lead->status === LeadStatus::Lost) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تحويل عميل محتمل تم تسجيله كخسارة.',
            ]);
        }

        // Everything happens in one transaction. If ANY step fails, we
        // rewind — the Lead stays untouched and no half-created Client
        // is left dangling.
        [$freshLead, $client, $case, $createdJobs] = DB::transaction(function () use ($lead, $payload, $actor) {
            // Row-lock the Lead so a concurrent request converting the
            // same row observes our commit and hits the Converted guard.
            $locked = $this->leads->findForUpdate($lead->id);

            $client = $this->resolveClient($locked, $payload, $actor);
            $case = $this->createCase($client, $locked, $payload);
            $jobs = $this->createJobs($case, $payload['jobs'] ?? []);

            $updatedLead = $this->leads->update($locked, [
                'status' => LeadStatus::Converted->value,
                'converted_at' => now(),
                'converted_client_id' => $client->id,
            ]);

            $this->activities->create([
                'lead_id' => $updatedLead->id,
                'user_id' => $actor->id,
                'type' => 'status_change',
                'subject' => 'تم تحويل العميل المحتمل إلى عميل فعلي',
                'occurred_at' => now(),
                'metadata' => [
                    'system' => true,
                    'client_id' => $client->id,
                    'case_id' => $case->id,
                    'job_count' => $jobs->count(),
                ],
            ]);

            return [$updatedLead, $client, $case, $jobs];
        });

        LeadConverted::dispatch($freshLead, $client, $case, $createdJobs);

        // Notifier failure must not undo the (already committed)
        // conversion — mirror the wave-n try/catch pattern.
        try {
            $caseOwner = $case->owner;
            if ($caseOwner instanceof User) {
                $this->notifier->leadConverted($freshLead, $client, $caseOwner, $freshLead->owner);
            }
        } catch (\Throwable $e) {
            Log::warning('leadConverted notifier failed', [
                'lead_id' => $freshLead->id,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'lead' => $freshLead,
            'client' => $client,
            'case' => $case,
            'jobs' => $createdJobs,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveClient(Lead $lead, array $payload, User $actor): Client
    {
        $reuseId = $payload['reuse_client_id'] ?? null;

        if ($reuseId !== null) {
            return $this->clients->findOrFail((int) $reuseId);
        }

        $clientPayload = (array) ($payload['client'] ?? []);
        $force = (bool) ($clientPayload['force'] ?? false);
        unset($clientPayload['force']);

        // Backfill fields not explicitly overridden by the Convert
        // payload from the source Lead — so "convert with only a case
        // title" still produces a properly stamped Client.
        $clientPayload = array_replace(
            [
                'company_name' => $lead->company_name,
                'company_website' => $lead->company_website,
                'industry' => $lead->industry,
                'company_size' => $lead->company_size,
                'country' => $lead->country,
                'city' => $lead->city,
            ],
            array_filter($clientPayload, fn ($v) => $v !== null && $v !== ''),
        );

        // Same dedup semantics as ClientService::create.
        if (! $force && $this->clients->duplicateExists(
            (string) $clientPayload['company_name'],
            $clientPayload['country'] ?? null,
        )) {
            throw ValidationException::withMessages([
                'client.company_name' => 'يوجد عميل بنفس الاسم والدولة. مرّر reuse_client_id للربط، أو client.force=true للحفظ الجديد.',
            ]);
        }

        $client = $this->clients->create([
            'client_number' => $this->numbers->nextClientNumber(),
            'company_name' => $clientPayload['company_name'],
            'company_website' => $clientPayload['company_website'] ?? null,
            'industry' => $clientPayload['industry'] ?? null,
            'company_size' => $clientPayload['company_size'] ?? null,
            'country' => $clientPayload['country'] ?? null,
            'city' => $clientPayload['city'] ?? null,
            'address' => $clientPayload['address'] ?? null,
            'tax_number' => $clientPayload['tax_number'] ?? null,
            'payment_terms' => $clientPayload['payment_terms'] ?? null,
            'account_manager_id' => $clientPayload['account_manager_id'] ?? $lead->owner_id ?? $actor->id,
            'source_lead_id' => $lead->id,
            'status' => 'active',
        ]);

        // Materialise the Lead's contact person as the client's primary
        // contact — the whole point of remembering contact_person on
        // Leads is that it survives conversion.
        if ($lead->contact_person !== null || $lead->contact_email !== null || $lead->contact_phone !== null) {
            $this->contacts->create($client, [
                'full_name' => $lead->contact_person ?? '—',
                'position' => $lead->contact_position,
                'email' => $lead->contact_email,
                'phone' => $lead->contact_phone,
                'linkedin_url' => $lead->linkedin_url,
                'is_primary' => true,
            ]);
        }

        return $client;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createCase(Client $client, Lead $lead, array $payload): RecruitmentCase
    {
        $casePayload = (array) $payload['case'];

        return $this->cases->create([
            'client_id' => $client->id,
            'source_lead_id' => $lead->id,
            'title' => $casePayload['title'],
            'description' => $casePayload['description'] ?? null,
            'owner_id' => $casePayload['owner_id'] ?? $lead->owner_id,
            'priority' => $casePayload['priority'] ?? 'normal',
            'status' => RecruitmentCaseStatus::Active->value,
            'target_hires' => $casePayload['target_hires'] ?? null,
            'started_at' => $casePayload['started_at'] ?? now()->toDateString(),
            'deadline' => $casePayload['deadline'] ?? null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $jobsPayload
     * @return Collection<int, \App\Models\JobRequirement>
     */
    private function createJobs(RecruitmentCase $case, array $jobsPayload): Collection
    {
        return collect($jobsPayload)
            ->map(fn (array $jobData) => $this->jobs->create(array_merge($jobData, [
                'recruitment_case_id' => $case->id,
                'owner_id' => $jobData['owner_id'] ?? $case->owner_id,
            ])))
            ->values();
    }
}
