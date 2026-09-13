<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Modules\Recruitment\Requests\StoreLeadActivityRequest;
use App\Modules\Recruitment\Resources\LeadActivityResource;
use App\Modules\Recruitment\Services\LeadActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Nested under /leads/{lead}/activities. System-generated timeline
 * entries (status_change, owner_change, created/deleted markers) are
 * written straight from LeadService — this controller only accepts what
 * a human deliberately logs.
 */
class LeadActivityController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly LeadActivityService $activities,
    ) {}

    public function index(HttpRequest $request, Lead $lead): AnonymousResourceCollection
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return LeadActivityResource::collection($this->activities->paginateForLead($lead, $perPage));
    }

    public function store(StoreLeadActivityRequest $request, Lead $lead): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $activity = $this->activities->create($lead, $request->validated(), $user);

        return (new LeadActivityResource($activity))->response()->setStatusCode(201);
    }
}
