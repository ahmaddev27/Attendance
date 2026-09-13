<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * How PipelineTaskGeneratorService resolves the assignee for the task
 * it generates when a JobRequirement enters a stage.
 *
 *   Role                -> first user carrying the permission named in
 *                          `owner_rule_value` (e.g. "publish-jobs")
 *   Specific            -> user id (owner_rule_value cast to int)
 *   CaseOwner           -> the enclosing RecruitmentCase's owner
 *   JobOwner            -> the JobRequirement's owner (the recruiter)
 *   PreviousStageOwner  -> whoever handled the immediately previous
 *                          stage's auto-generated task
 *   None                -> no task is generated (terminal stages such
 *                          as Hired / Cancelled)
 *
 * Persisted as a VARCHAR on recruitment_pipeline_stages so a new rule
 * type doesn't require a schema migration — this enum is the reference
 * list only, not a DB constraint.
 */
enum StageOwnerRule: string
{
    case Role = 'role';
    case Specific = 'specific';
    case CaseOwner = 'case_owner';
    case JobOwner = 'job_owner';
    case PreviousStageOwner = 'previous_stage_owner';
    case None = 'none';
}
