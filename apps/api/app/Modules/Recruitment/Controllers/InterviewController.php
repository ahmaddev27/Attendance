<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CandidateApplication;
use App\Models\Interview;
use App\Modules\Recruitment\Repositories\InterviewRepository;
use App\Modules\Recruitment\Requests\CancelInterviewRequest;
use App\Modules\Recruitment\Requests\RescheduleInterviewRequest;
use App\Modules\Recruitment\Requests\StoreInterviewRequest;
use App\Modules\Recruitment\Requests\UpdateInterviewRequest;
use App\Modules\Recruitment\Resources\InterviewResource;
use App\Modules\Recruitment\Services\InterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InterviewController extends Controller
{
    public function __construct(
        private readonly InterviewService $interviews,
        private readonly InterviewRepository $repository,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'application_id',
            'status',
            'kind',
            'include_closed',
            'from',
            'until',
        ]);
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(1, min($perPage, 100));

        return InterviewResource::collection($this->repository->paginate($filters, $perPage));
    }

    public function store(StoreInterviewRequest $request, CandidateApplication $application): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $interview = $this->interviews->schedule($application, $request->validated(), $actor);

        return InterviewResource::make($interview)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Interview $interview): InterviewResource
    {
        return InterviewResource::make($this->repository->findOrFail($interview->id));
    }

    public function update(UpdateInterviewRequest $request, Interview $interview): InterviewResource
    {
        return InterviewResource::make(
            $this->interviews->update($interview, $request->validated()),
        );
    }

    public function cancel(CancelInterviewRequest $request, Interview $interview): InterviewResource
    {
        return InterviewResource::make(
            $this->interviews->cancel($interview, $request->validated('reason')),
        );
    }

    public function reschedule(RescheduleInterviewRequest $request, Interview $interview): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $replacement = $this->interviews->reschedule($interview, $request->validated(), $actor);

        return InterviewResource::make($replacement)
            ->response()
            ->setStatusCode(201);
    }

    public function complete(Interview $interview): InterviewResource
    {
        return InterviewResource::make($this->interviews->complete($interview));
    }
}
