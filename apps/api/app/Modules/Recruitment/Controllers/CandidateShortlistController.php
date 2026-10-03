<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CandidateApplication;
use App\Models\JobRequirement;
use App\Modules\Recruitment\Resources\CandidateApplicationResource;
use App\Modules\Recruitment\Services\CandidateShortlistService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidateShortlistController extends Controller
{
    public function __construct(
        private readonly CandidateShortlistService $shortlist,
    ) {}

    public function add(Request $request, CandidateApplication $application): CandidateApplicationResource
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        return CandidateApplicationResource::make($this->shortlist->add($application, $actor));
    }

    public function remove(CandidateApplication $application): CandidateApplicationResource
    {
        return CandidateApplicationResource::make($this->shortlist->remove($application));
    }

    public function indexForJob(JobRequirement $job): AnonymousResourceCollection
    {
        return CandidateApplicationResource::collection($this->shortlist->paginateForJob($job));
    }
}
