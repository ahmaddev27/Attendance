<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Which business object a Task is "about" — a lightweight, string-keyed
 * polymorphic tag stored on tasks.entity_type. Deliberately not a
 * morph-map to a model class so a Model rename doesn't invalidate
 * historical rows; the string here is the durable identifier.
 *
 * Phase 1 recognises only the four Recruitment entities; candidates,
 * interviews, and contracts appear in later phases and their cases
 * will be added then.
 */
enum TaskEntityType: string
{
    case Lead = 'lead';
    case Client = 'client';
    case RecruitmentCase = 'recruitment_case';
    case JobRequirement = 'job_requirement';

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
        };
    }
}
