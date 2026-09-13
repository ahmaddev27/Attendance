<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Events;

use App\Models\JobRequirement;
use App\Models\RecruitmentPipelineStage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after JobRequirementService::advanceStage() commits the
 * transition. The PipelineTaskGenerator listens for this to spawn the
 * "next-stage" task; other consumers (broadcast, analytics) can hang
 * off the same event without knowing about task generation.
 */
class JobRequirementStageAdvanced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JobRequirement $job,
        public readonly ?RecruitmentPipelineStage $fromStage,
        public readonly RecruitmentPipelineStage $toStage,
    ) {}
}
