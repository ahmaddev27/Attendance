<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Modules\Recruitment\Repositories\InterviewFeedbackRepository;
use App\Modules\Recruitment\Requests\StoreInterviewFeedbackRequest;
use App\Modules\Recruitment\Resources\InterviewFeedbackResource;
use App\Modules\Recruitment\Services\InterviewFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InterviewFeedbackController extends Controller
{
    public function __construct(
        private readonly InterviewFeedbackService $service,
        private readonly InterviewFeedbackRepository $feedbacks,
    ) {}

    public function index(Interview $interview): AnonymousResourceCollection
    {
        return InterviewFeedbackResource::collection($this->feedbacks->forInterview($interview));
    }

    public function store(StoreInterviewFeedbackRequest $request, Interview $interview): JsonResponse
    {
        /** @var \App\Models\User $interviewer */
        $interviewer = $request->user();

        $feedback = $this->service->submit($interview, $interviewer, $request->validated());

        return InterviewFeedbackResource::make($feedback)
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreInterviewFeedbackRequest $request, Interview $interview, InterviewFeedback $feedback): InterviewFeedbackResource
    {
        // Scope guard — a feedback id can only be edited through the
        // interview that owns it. Prevents cross-interview id probing.
        abort_unless($feedback->interview_id === $interview->id, 404);

        /** @var \App\Models\User $interviewer */
        $interviewer = $request->user();

        $updated = $this->service->submit($interview, $interviewer, $request->validated());

        return InterviewFeedbackResource::make($updated);
    }
}
