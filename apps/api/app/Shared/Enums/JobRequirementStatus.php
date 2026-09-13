<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Coarse-grained lifecycle for a JobRequirement, orthogonal to its
 * fine-grained current_stage_id inside the pipeline.
 *
 *   Draft     -> created, not yet moved out of the first pipeline stage
 *   Active    -> currently running through the pipeline
 *   OnHold    -> intentionally paused by the client or the recruiter
 *   Filled    -> the last openings hire signed a contract
 *   Cancelled -> client rescinded the requirement, no candidates hired
 *
 * status advances into Filled/Cancelled only through a terminal
 * pipeline stage transition — see JobRequirementService::advanceStage().
 */
enum JobRequirementStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Filled = 'filled';
    case Cancelled = 'cancelled';
}
