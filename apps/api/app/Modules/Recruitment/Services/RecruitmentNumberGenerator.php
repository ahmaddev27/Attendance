<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Modules\Recruitment\Repositories\CandidateApplicationRepository;
use App\Modules\Recruitment\Repositories\CandidateRepository;
use App\Modules\Recruitment\Repositories\ClientRepository;
use App\Modules\Recruitment\Repositories\InterviewRepository;
use App\Modules\Recruitment\Repositories\JobRequirementRepository;
use App\Modules\Recruitment\Repositories\LeadRepository;
use App\Modules\Recruitment\Repositories\RecruitmentCaseRepository;

/**
 * Mints the human-facing sequential identifiers each Recruitment
 * entity carries:
 *   Phase 1 — L-YYYY-####, C-YYYY-####, RC-YYYY-####, J-YYYY-####.
 *   Phase 2 — CAN-YYYY-####, APP-YYYY-#####, INT-YYYY-#####.
 *
 * Sequences restart at 0001 every calendar year. Must be called from
 * INSIDE the DB::transaction that inserts the row — the repository
 * reads the highest existing number, so two concurrent transactions
 * both trying to mint the same year's next id could otherwise pick
 * the same value. The MySQL UNIQUE index on the *_number column is
 * the last line of defense; use a row-level lock or a service-level
 * cache::lock in the caller if very high concurrency is expected.
 */
class RecruitmentNumberGenerator
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly ClientRepository $clients,
        private readonly RecruitmentCaseRepository $cases,
        private readonly JobRequirementRepository $jobs,
        private readonly CandidateRepository $candidates,
        private readonly CandidateApplicationRepository $applications,
        private readonly InterviewRepository $interviews,
    ) {}

    public function nextLeadNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');

        return $this->format('L', $year, $this->leads->highestSequenceForYear($year) + 1);
    }

    public function nextClientNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');

        return $this->format('C', $year, $this->clients->highestSequenceForYear($year) + 1);
    }

    public function nextCaseNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');

        return $this->format('RC', $year, $this->cases->highestSequenceForYear($year) + 1);
    }

    public function nextJobNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');

        return $this->format('J', $year, $this->jobs->highestSequenceForYear($year) + 1);
    }

    public function nextCandidateNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');

        return $this->format('CAN', $year, $this->candidates->highestSequenceForYear($year) + 1);
    }

    public function nextApplicationNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');

        return $this->format('APP', $year, $this->applications->highestSequenceForYear($year) + 1, width: 5);
    }

    public function nextInterviewNumber(?int $year = null): string
    {
        $year ??= (int) date('Y');

        return $this->format('INT', $year, $this->interviews->highestSequenceForYear($year) + 1, width: 5);
    }

    private function format(string $prefix, int $year, int $sequence, int $width = 4): string
    {
        return sprintf("%s-%d-%0{$width}d", $prefix, $year, $sequence);
    }
}
