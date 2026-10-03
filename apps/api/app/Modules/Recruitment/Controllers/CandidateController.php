<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Modules\Recruitment\Requests\StoreCandidateRequest;
use App\Modules\Recruitment\Requests\UpdateCandidateRequest;
use App\Modules\Recruitment\Resources\CandidateApplicationResource;
use App\Modules\Recruitment\Resources\CandidateResource;
use App\Modules\Recruitment\Services\CandidateApplicationService;
use App\Modules\Recruitment\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidateController extends Controller
{
    public function __construct(
        private readonly CandidateService $candidates,
        private readonly CandidateApplicationService $applications,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'status', 'source', 'country', 'has_resume', 'min_experience', 'include_closed']);
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(1, min($perPage, 100));

        return CandidateResource::collection($this->candidates->paginate($filters, $perPage));
    }

    public function store(StoreCandidateRequest $request): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $candidate = $this->candidates->create($request->validated(), $actor);

        return CandidateResource::make($candidate->fresh(['createdByUser']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Candidate $candidate): CandidateResource
    {
        return CandidateResource::make($this->candidates->find($candidate->id));
    }

    public function update(UpdateCandidateRequest $request, Candidate $candidate): CandidateResource
    {
        return CandidateResource::make($this->candidates->update($candidate, $request->validated()));
    }

    public function destroy(Candidate $candidate): JsonResponse
    {
        $this->candidates->softDelete($candidate);

        return response()->json(null, 204);
    }

    public function applications(Candidate $candidate): AnonymousResourceCollection
    {
        return CandidateApplicationResource::collection(
            $this->applications->paginateForCandidate($candidate),
        );
    }
}
