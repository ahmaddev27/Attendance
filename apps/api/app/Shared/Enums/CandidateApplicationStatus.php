<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Status marker on a CandidateApplication — tracks the APPLICATION's
 * progress inside a job's pipeline. Separate from CandidateStatus (which
 * is about the person) and from the pipeline stage (which is about the
 * admin's workflow): a candidate can be `interviewing` while sitting in
 * the `screening` stage because the admin advanced them manually.
 */
enum CandidateApplicationStatus: string
{
    case Applied = 'applied';
    case InScreening = 'in_screening';
    case ScreenedIn = 'screened_in';
    case ScreenedOut = 'screened_out';
    case Shortlisted = 'shortlisted';
    case Interviewing = 'interviewing';
    case ClientReview = 'client_review';
    case Offered = 'offered';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Hired = 'hired';

    /**
     * True for terminal states — a row in one of these never advances
     * again. Used by the service layer to block stage transitions on
     * closed applications instead of silently discarding them.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Withdrawn, self::Hired], true);
    }
}
