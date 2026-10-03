<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\JobRequirement;
use App\Modules\Recruitment\Requests\AttachCandidateToJobRequest;
use App\Modules\Recruitment\Requests\RejectCandidateApplicationRequest;
use App\Modules\Recruitment\Requests\UpdateCandidateApplicationRequest;
use App\Modules\Recruitment\Resources\CandidateApplicationResource;
use App\Modules\Recruitment\Services\CandidateApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidateApplicationController extends Controller
{
    public function __construct(
        private readonly CandidateApplicationService $applications,
    ) {}

    public function indexForJob(Request $request, JobRequirement $job): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'is_shortlisted', 'stage_id', 'source', 'search', 'include_closed']);
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(1, min($perPage, 100));

        return CandidateApplicationResource::collection(
            $this->applications->paginateForJob($job, $filters, $perPage),
        );
    }

    public function attach(AttachCandidateToJobRequest $request, JobRequirement $job): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $candidate = Candidate::query()->findOrFail((int) $request->validated('candidate_id'));

        $application = $this->applications->attach(
            $job,
            $candidate,
            $actor,
            ['source' => $request->validated('source') ?? 'manual', 'notes' => $request->validated('notes')],
        );

        return CandidateApplicationResource::make($application)
            ->response()
            ->setStatusCode(201);
    }

    public function show(CandidateApplication $application): CandidateApplicationResource
    {
        return CandidateApplicationResource::make($this->applications->find($application->id));
    }

    public function update(UpdateCandidateApplicationRequest $request, CandidateApplication $application): CandidateApplicationResource
    {
        return CandidateApplicationResource::make(
            $this->applications->update($application, $request->validated()),
        );
    }

    public function reject(RejectCandidateApplicationRequest $request, CandidateApplication $application): CandidateApplicationResource
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        return CandidateApplicationResource::make(
            $this->applications->reject($application, $actor, (string) $request->validated('reason')),
        );
    }

    public function withdraw(CandidateApplication $application): CandidateApplicationResource
    {
        return CandidateApplicationResource::make($this->applications->withdraw($application));
    }
}
