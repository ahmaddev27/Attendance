<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Lifecycle of a RecruitmentCase (one hiring campaign under a Client).
 *
 *   Draft     -> being planned; no jobs opened yet
 *   Active    -> jobs are live and moving through the pipeline
 *   OnHold    -> client paused the whole campaign (budget, priorities)
 *   Completed -> all requested hires filled (or client accepted less)
 *   Cancelled -> client withdrew the campaign; no hires
 *
 * The case's own status is independent of the individual JobRequirement
 * statuses beneath it — see RecruitmentCaseService for the roll-up
 * rules (a Case is auto-Completed only when every non-cancelled job
 * has status = Filled).
 */
enum RecruitmentCaseStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
