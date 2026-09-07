<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Services;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Modules\Workflow\Repositories\WorkflowRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    /**
     * step_order values are temporarily bumped by this much during
     * reorderSteps() so the (workflow_id, step_order) unique index never
     * sees a collision while steps are mid-swap. Comfortably above any
     * realistic step count.
     */
    private const REORDER_OFFSET = 100000;

    public function __construct(
        private readonly WorkflowRepository $workflows,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->workflows->paginate($filters, $perPage);
    }

    public function find(int $id): Workflow
    {
        return $this->workflows->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Workflow
    {
        return $this->workflows->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Workflow $workflow, array $data): Workflow
    {
        return $this->workflows->update($workflow, $data);
    }

    /**
     * Deactivating (is_active = false) is always safe and is how a
     * workflow already backing one or more request types should be
     * retired — deleting it would either hit the request_types.workflow_id
     * restrictOnDelete constraint or, for a never-used workflow, is simply
     * allowed outright.
     */
    public function delete(Workflow $workflow): void
    {
        if ($workflow->requestTypes()->exists()) {
            throw ValidationException::withMessages([
                'workflow' => 'This workflow cannot be deleted because one or more request types use it. Deactivate it instead.',
            ]);
        }

        $this->workflows->delete($workflow);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addStep(Workflow $workflow, array $data): WorkflowStep
    {
        $data['step_order'] ??= ((int) $workflow->steps()->max('step_order')) + 1;

        return $workflow->steps()->create($data);
    }

    public function findStepForWorkflow(Workflow $workflow, int $stepId): WorkflowStep
    {
        return $workflow->steps()->findOrFail($stepId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStep(WorkflowStep $step, array $data): WorkflowStep
    {
        // Reordering is a dedicated, all-or-nothing operation (see
        // reorderSteps()) so the unique(workflow_id, step_order) index is
        // never at risk of a partial, order-breaking update here.
        unset($data['step_order'], $data['workflow_id']);

        $step->update($data);

        return $step->fresh();
    }

    public function removeStep(WorkflowStep $step): void
    {
        $step->delete();
    }

    /**
     * @param  list<array{id: int, step_order: int}>  $orderedSteps
     * @return Collection<int, WorkflowStep>
     */
    public function reorderSteps(Workflow $workflow, array $orderedSteps): Collection
    {
        return DB::transaction(function () use ($workflow, $orderedSteps) {
            $stepIds = array_column($orderedSteps, 'id');

            $steps = $workflow->steps()->whereIn('id', $stepIds)->lockForUpdate()->get()->keyBy('id');

            if ($steps->count() !== count($orderedSteps)) {
                throw ValidationException::withMessages([
                    'steps' => 'One or more steps do not belong to this workflow.',
                ]);
            }

            // Phase 1: push every affected step out of the way of the
            // unique(workflow_id, step_order) index before assigning any
            // final values — otherwise swapping two steps' orders would
            // momentarily collide.
            foreach (array_values($stepIds) as $index => $stepId) {
                $steps[$stepId]->update(['step_order' => self::REORDER_OFFSET + $index]);
            }

            foreach ($orderedSteps as $item) {
                $steps[$item['id']]->update(['step_order' => $item['step_order']]);
            }

            return $workflow->steps()->get();
        });
    }
}
