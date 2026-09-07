<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Shared\Enums\ApproverType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    protected $model = WorkflowStep::class;

    /**
     * Defaults to a `specific_employee` step referencing no one in
     * particular (approver_ref left null) — tests that care about a
     * concrete approver override it explicitly via ->state() or
     * ->approverType(), since "who can approve this" is almost always
     * the point of a test that touches this factory.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'step_order' => 1,
            'name' => ucfirst($this->faker->words(3, true)),
            'approver_type' => ApproverType::SpecificEmployee,
            'approver_ref' => null,
            'can_reject' => true,
            'can_return' => false,
            'can_forward' => false,
            'sla_hours' => null,
        ];
    }

    public function approverType(ApproverType $type, ?string $ref = null): static
    {
        return $this->state(fn (array $attributes) => [
            'approver_type' => $type,
            'approver_ref' => $ref,
        ]);
    }

    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => ['step_order' => $order]);
    }
}
