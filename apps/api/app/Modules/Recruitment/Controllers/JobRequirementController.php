<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Modules\Recruitment\Requests\AdvanceJobStageRequest;
use App\Modules\Recruitment\Requests\StoreJobRequirementRequest;
use App\Modules\Recruitment\Requests\UpdateJobRequirementRequest;
use App\Modules\Recruitment\Resources\JobRequirementResource;
use App\Modules\Recruitment\Services\JobRequirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobRequirementController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly JobRequirementService $jobs,
    ) {}

    public function index(HttpRequest $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'recruitment_case_id',
            'client_id',
            'pipeline_id',
            'current_stage_id',
            'owner_id',
            'status',
            'employment_type',
            'work_mode',
            'search',
        ]);

        if ($request->boolean('open_only')) {
            $filters['open_only'] = true;
        }

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return JobRequirementResource::collection($this->jobs->paginate($filters, $perPage));
    }

    public function show(JobRequirement $job): JobRequirementResource
    {
        return new JobRequirementResource($this->jobs->find($job->id));
    }

    public function store(StoreJobRequirementRequest $request): JsonResponse
    {
        $job = $this->jobs->create($request->validated());

        return (new JobRequirementResource($job))->response()->setStatusCode(201);
    }

    public function update(UpdateJobRequirementRequest $request, JobRequirement $job): JobRequirementResource
    {
        return new JobRequirementResource($this->jobs->update($job, $request->validated()));
    }

    public function destroy(JobRequirement $job): JsonResponse
    {
        $this->jobs->delete($job);

        return response()->json(null, 204);
    }

    /**
     * Move a job to the next pipeline stage. The permission gate on the
     * route (`advance-job-stage`) admits anyone allowed to advance ANY
     * stage; the service further validates the caller owns the source
     * stage before the transition commits.
     */
    public function advanceStage(AdvanceJobStageRequest $request, JobRequirement $job): JobRequirementResource
    {
        return new JobRequirementResource($this->jobs->advanceStage($job, $request->validated()));
    }

    public function cancel(JobRequirement $job): JobRequirementResource
    {
        return new JobRequirementResource($this->jobs->cancel($job));
    }

    /**
     * Nested: every job belonging to a specific case, paginated.
     */
    public function indexForCase(HttpRequest $request, RecruitmentCase $case): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return JobRequirementResource::collection($this->jobs->paginateForCase($case, $perPage));
    }
}
