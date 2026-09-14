<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\RecruitmentCase;
use App\Models\User;
use App\Modules\Recruitment\Integrations\BrightGaza\BrightGazaJobFeed;
use App\Modules\Recruitment\Repositories\ClientRepository;
use App\Modules\Recruitment\Repositories\JobRequirementRepository;
use App\Modules\Recruitment\Repositories\RecruitmentCaseRepository;
use App\Modules\Recruitment\Repositories\RecruitmentPipelineRepository;
use App\Shared\Enums\JobRequirementStatus;
use App\Shared\Enums\RecruitmentCaseStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pulls the jobs listed on BrightGaza's public board into TAQAT's pipeline.
 * Owner decision (2026-09-14): no API integration yet, only a manual pull
 * that keeps everything BrightGaza shows. Jobs are matched on BrightGaza's
 * id, so pulling again refreshes them without duplicates and never undoes
 * the stage, status or owner work done in TAQAT.
 */
class BrightGazaJobImportService
{
    public const SOURCE = 'brightgaza';

    /** Marks imported jobs that no longer appear on the board. */
    public const NOT_LISTED = 'not_listed';

    private const CLIENT_NAME = 'BrightGaza';

    private const CASE_TITLE = 'وظائف مسحوبة من BrightGaza';

    /** A job on the board is already published and taking proposals. */
    private const ENTRY_STAGE = 'receiving_apps';

    public function __construct(
        private readonly BrightGazaJobFeed $feed,
        private readonly JobRequirementRepository $jobs,
        private readonly ClientRepository $clients,
        private readonly RecruitmentCaseRepository $cases,
        private readonly RecruitmentPipelineRepository $pipelines,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @return array{fetched: int, created: int, updated: int, skipped: int, not_listed: int}
     */
    public function import(User $actor): array
    {
        // The whole board is read before anything is written, so an outage
        // halfway through the pages leaves TAQAT untouched.
        $listings = $this->feed->openJobs();

        $pipeline = $this->pipelines->default();
        $entryStage = $pipeline?->stages->firstWhere('code', self::ENTRY_STAGE) ?? $pipeline?->firstStage();

        if ($pipeline === null || $entryStage === null) {
            throw ValidationException::withMessages([
                'pipeline_id' => 'لا يوجد مسار توظيف افتراضي مفعّل لاستقبال الوظائف المسحوبة.',
            ]);
        }

        $case = $this->importCase($actor);
        $summary = ['fetched' => count($listings), 'created' => 0, 'updated' => 0, 'skipped' => 0, 'not_listed' => 0];
        $listedIds = [];

        foreach ($listings as $listing) {
            $externalId = isset($listing['id']) && is_scalar($listing['id']) ? (string) $listing['id'] : '';

            if ($externalId === '') {
                $summary['skipped']++;

                continue;
            }

            $listedIds[] = $externalId;
            $existing = $this->jobs->findByExternalReference(self::SOURCE, $externalId);

            if ($existing?->trashed()) {
                // Deleted in TAQAT on purpose; a pull must not bring it back.
                $summary['skipped']++;

                continue;
            }

            if ($existing !== null) {
                $this->jobs->update($existing, $this->listingAttributes($listing));
                $summary['updated']++;

                continue;
            }

            DB::transaction(fn () => $this->jobs->create([
                ...$this->listingAttributes($listing),
                'job_number' => $this->numbers->nextJobNumber(),
                'recruitment_case_id' => $case->id,
                'pipeline_id' => $pipeline->id,
                'current_stage_id' => $entryStage->id,
                'stage_entered_at' => now(),
                'owner_id' => $actor->id,
                'status' => JobRequirementStatus::Active->value,
                'external_source' => self::SOURCE,
                'external_id' => $externalId,
            ]));

            $summary['created']++;
        }

        $summary['not_listed'] = $this->jobs->markExternalNotListed(self::SOURCE, $listedIds, self::NOT_LISTED);

        return $summary;
    }

    /**
     * The fields TAQAT has columns for are copied out; the complete listing
     * (category, contract type, experience level, proposal count, poster,
     * milestones...) is kept in external_payload.
     *
     * @param  array<string, mixed>  $listing
     * @return array<string, mixed>
     */
    private function listingAttributes(array $listing): array
    {
        $title = trim((string) ($listing['title'] ?? ''));

        return [
            'title' => mb_substr($title !== '' ? $title : 'BrightGaza #'.$listing['id'], 0, 200),
            'description' => $this->plainText($listing['description'] ?? null),
            'required_skills' => $this->names($listing['skills'] ?? []),
            'openings' => max(1, (int) ($listing['number_of_open_positions'] ?? 1)),
            // Board jobs are remote freelance engagements, hourly or fixed
            // price (external_payload.contract_time_type says which).
            'employment_type' => 'contract',
            'work_mode' => 'remote',
            'location' => $this->location($listing['allow_countries'] ?? []),
            'salary_min' => $this->amount($listing['budget_from'] ?? null),
            'salary_max' => $this->amount($listing['budget_to'] ?? null),
            'salary_currency' => 'USD',
            'publication_url' => rtrim((string) config('services.brightgaza.site_url'), '/').'/ar/jobs/'.$listing['id'],
            'published_at' => $this->timestamp($listing['created_at'] ?? null),
            'external_status' => $this->statusName($listing['status'] ?? null),
            'external_payload' => $listing,
            'external_synced_at' => now(),
        ];
    }

    private function importCase(User $actor): RecruitmentCase
    {
        return DB::transaction(function () use ($actor): RecruitmentCase {
            $client = $this->clients->findByCompany(self::CLIENT_NAME, null)
                ?? $this->clients->create([
                    'client_number' => $this->numbers->nextClientNumber(),
                    'company_name' => self::CLIENT_NAME,
                    'company_website' => (string) config('services.brightgaza.site_url'),
                    'account_manager_id' => $actor->id,
                    'status' => 'active',
                ]);

            return $this->cases->findForClientByTitle($client->id, self::CASE_TITLE)
                ?? $this->cases->create([
                    'case_number' => $this->numbers->nextCaseNumber(),
                    'client_id' => $client->id,
                    'title' => self::CASE_TITLE,
                    'owner_id' => $actor->id,
                    'status' => RecruitmentCaseStatus::Active->value,
                ]);
        });
    }

    private function plainText(mixed $html): ?string
    {
        if (! is_string($html) || trim($html) === '') {
            return null;
        }

        // Keep paragraph and list breaks readable once the tags are gone.
        $withBreaks = preg_replace('/<\s*(br|\/p|\/li|\/h[1-6]|\/div)\s*\/?>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * @return list<string>
     */
    private function names(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $names = [];

        foreach ($items as $item) {
            $name = is_array($item) ? ($item['name'] ?? null) : $item;

            if (is_scalar($name) && trim((string) $name) !== '') {
                $names[] = trim((string) $name);
            }
        }

        return $names;
    }

    private function location(mixed $countries): ?string
    {
        $names = $this->names($countries);

        return $names === [] ? null : mb_substr(implode('، ', $names), 0, 200);
    }

    private function amount(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function statusName(mixed $status): ?string
    {
        $name = is_array($status) ? ($status['name'] ?? null) : $status;

        return is_scalar($name) ? (string) $name : null;
    }
}
