<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Which business object a Task is "about" — a lightweight, string-keyed
 * polymorphic tag stored on tasks.entity_type. Deliberately not a
 * morph-map to a model class so a Model rename doesn't invalidate
 * historical rows; the string here is the durable identifier.
 *
 * Phase 1 cases: Lead / Client / RecruitmentCase / JobRequirement.
 * Phase 2 adds the ATS entities (Candidate, CandidateApplication,
 * Interview) — contracts are still deferred to Phase 3.
 */
enum TaskEntityType: string
{
    case Lead = 'lead';
    case Client = 'client';
    case RecruitmentCase = 'recruitment_case';
    case JobRequirement = 'job_requirement';
    case Candidate = 'candidate';
    case CandidateApplication = 'candidate_application';
    case Interview = 'interview';

    /**
     * Frontend route of the entity — the one place the web app's URL
     * shape for Recruitment objects is spelled out on the API side.
     */
    public function route(int $id): string
    {
        return match ($this) {
            self::Lead => "/recruitment/leads/{$id}",
            self::Client => "/recruitment/clients/{$id}",
            self::RecruitmentCase => "/recruitment/cases/{$id}",
            self::JobRequirement => "/recruitment/jobs/{$id}",
            self::Candidate => "/recruitment/candidates/{$id}",
            self::CandidateApplication => "/recruitment/applications/{$id}",
            self::Interview => "/recruitment/interviews/{$id}",
        };
    }
}
