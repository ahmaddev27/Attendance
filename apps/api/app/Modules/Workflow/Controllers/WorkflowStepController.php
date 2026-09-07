<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Modules\Workflow\Requests\ReorderStepsRequest;
use App\Modules\Workflow\Requests\StoreWorkflowStepRequest;
use App\Modules\Workflow\Requests\UpdateWorkflowStepRequest;
use App\Modules\Workflow\Resources\WorkflowStepResource;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Nested under /workflows/{workflow}/steps. Every method re-resolves the
 * {step} route-bound model against $workflow via
 * WorkflowService::findStepForWorkflow() (a plain global lookup + 404),
 * rather than trusting the implicit binding alone — otherwise a step id
 * belonging to a *different* workflow would still resolve successfully.
 */
class WorkflowStepController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflows,
    ) {}

    public function index(Workflow $workflow): AnonymousResourceCollection
    {
        return WorkflowStepResource::collection($this->workflows->find($workflow->id)->steps);
    }

    public function store(StoreWorkflowStepRequest $request, Workflow $workflow): JsonResponse
    {
        $step = $this->workflows->addStep($workflow, $request->validated());

        return (new WorkflowStepResource($step))->response()->setStatusCode(201);
    }

    public function show(Workflow $workflow, WorkflowStep $step): WorkflowStepResource
    {
        return new WorkflowStepResource($this->workflows->findStepForWorkflow($workflow, $step->id));
    }

    public function update(UpdateWorkflowStepRequest $request, Workflow $workflow, WorkflowStep $step): WorkflowStepResource
    {
        $step = $this->workflows->findStepForWorkflow($workflow, $step->id);

        return new WorkflowStepResource($this->workflows->updateStep($step, $request->validated()));
    }

    public function destroy(Workflow $workflow, WorkflowStep $step): JsonResponse
    {
        $step = $this->workflows->findStepForWorkflow($workflow, $step->id);

        $this->workflows->removeStep($step);

        return response()->json(null, 204);
    }

    public function reorder(ReorderStepsRequest $request, Workflow $workflow): AnonymousResourceCollection
    {
        $steps = $this->workflows->reorderSteps($workflow, $request->validated('steps'));

        return WorkflowStepResource::collection($steps);
    }
}
