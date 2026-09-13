<?php

declare(strict_types=1);

namespace App\Modules\Recruitment;

use App\Modules\Recruitment\Events\JobRequirementStageAdvanced;
use App\Modules\Recruitment\Services\PipelineTaskGeneratorService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Pipeline Engine's one non-obvious edge: whenever a job
 * enters a new stage, the PipelineTaskGenerator gets a chance to spawn
 * the auto-task for the next owner. Kept as a dedicated provider (as
 * opposed to inline in AppServiceProvider) so the Recruitment module
 * stays self-contained — pulling the provider out of
 * bootstrap/providers.php disables every bit of Recruitment automation
 * without touching anything else.
 */
class RecruitmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            JobRequirementStageAdvanced::class,
            [PipelineTaskGeneratorService::class, 'handle'],
        );
    }
}
