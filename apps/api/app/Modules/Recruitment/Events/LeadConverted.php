<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\Client;
use App\Models\Lead;
use App\Models\RecruitmentCase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Fired AFTER the conversion transaction commits (never inside it, so a
 * listener error can't roll back the creation). Carries the Case that
 * was opened plus every JobRequirement created in the same call, so the
 * PipelineTaskGeneratorService listener can spawn the "publish" tasks
 * for each new job without a follow-up query.
 */
class LeadConverted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\JobRequirement>  $jobs
     */
    public function __construct(
        public readonly Lead $lead,
        public readonly Client $client,
        public readonly RecruitmentCase $case,
        public readonly Collection $jobs,
    ) {}
}
