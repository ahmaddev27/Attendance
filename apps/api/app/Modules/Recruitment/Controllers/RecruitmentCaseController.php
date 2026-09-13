<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\RecruitmentCase;
use App\Modules\Recruitment\Requests\StoreRecruitmentCaseRequest;
use App\Modules\Recruitment\Requests\UpdateRecruitmentCaseRequest;
use App\Modules\Recruitment\Resources\RecruitmentCaseResource;
use App\Modules\Recruitment\Services\RecruitmentCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecruitmentCaseController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly RecruitmentCaseService $cases,
    ) {}

    public function index(HttpRequest $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'client_id',
            'owner_id',
            'status',
            'priority',
            'search',
        ]);

        if ($request->boolean('open_only')) {
            $filters['open_only'] = true;
        }

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return RecruitmentCaseResource::collection($this->cases->paginate($filters, $perPage));
    }

    public function show(RecruitmentCase $case): RecruitmentCaseResource
    {
        return new RecruitmentCaseResource($this->cases->find($case->id));
    }

    public function store(StoreRecruitmentCaseRequest $request): JsonResponse
    {
        $case = $this->cases->create($request->validated());

        return (new RecruitmentCaseResource($case))->response()->setStatusCode(201);
    }

    public function update(UpdateRecruitmentCaseRequest $request, RecruitmentCase $case): RecruitmentCaseResource
    {
        return new RecruitmentCaseResource($this->cases->update($case, $request->validated()));
    }

    public function destroy(RecruitmentCase $case): JsonResponse
    {
        $this->cases->delete($case);

        return response()->json(null, 204);
    }

    /**
     * Nested: every case belonging to a specific client, paginated.
     */
    public function indexForClient(HttpRequest $request, Client $client): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return RecruitmentCaseResource::collection($this->cases->paginateForClient($client, $perPage));
    }
}
