<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Modules\Workflow\Requests\StoreWorkflowRequest;
use App\Modules\Workflow\Requests\UpdateWorkflowRequest;
use App\Modules\Workflow\Resources\WorkflowResource;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkflowController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly WorkflowService $workflows,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['is_active', 'search']);
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return WorkflowResource::collection($this->workflows->paginate($filters, $perPage));
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $workflow = $this->workflows->create($request->validated());

        return (new WorkflowResource($workflow))->response()->setStatusCode(201);
    }

    public function show(Workflow $workflow): WorkflowResource
    {
        return new WorkflowResource($this->workflows->find($workflow->id));
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow): WorkflowResource
    {
        return new WorkflowResource($this->workflows->update($workflow, $request->validated()));
    }

    public function destroy(Workflow $workflow): JsonResponse
    {
        $this->workflows->delete($workflow);

        return response()->json(null, 204);
    }
}
