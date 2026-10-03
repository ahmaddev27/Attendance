<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CandidateApplication;
use App\Models\RecruitmentPipeline;
use App\Models\RecruitmentPipelineStage;
use App\Modules\Recruitment\Requests\StoreCandidateScreeningRequest;
use App\Modules\Recruitment\Resources\CandidateScreeningResource;
use App\Modules\Recruitment\Services\CandidateScreeningService;
use Illuminate\Http\JsonResponse;

class CandidateScreeningController extends Controller
{
    public function __construct(
        private readonly CandidateScreeningService $screening,
    ) {}

    public function show(CandidateApplication $application): JsonResponse
    {
        $screening = $this->screening->find($application);

        if ($screening === null) {
            return response()->json(['data' => null]);
        }

        return CandidateScreeningResource::make($screening->load('scoredBy'))->response();
    }

    public function store(StoreCandidateScreeningRequest $request, CandidateApplication $application): CandidateScreeningResource
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $screening = $this->screening->store(
            $application,
            $actor,
            (array) $request->validated('scorecard'),
            $request->validated('notes'),
        );

        return CandidateScreeningResource::make($screening->load('scoredBy'));
    }

    public function schema(RecruitmentPipeline $pipeline, RecruitmentPipelineStage $stage): JsonResponse
    {
        // Scope guard: the stage must belong to the pipeline in the URL
        // — otherwise an admin on one tenant's pipeline could probe the
        // other's stage schemas by swapping ids.
        abort_unless($stage->pipeline_id === $pipeline->id, 404);

        return response()->json(['data' => $this->screening->schemaForStage($stage)]);
    }
}
