<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\RequestApproval;
use App\Models\WorkflowStep;
use App\Shared\Enums\ApprovalAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestApproval>
 */
class RequestApprovalFactory extends Factory
{
    protected $model = RequestApproval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => RequestModel::factory(),
            'workflow_step_id' => WorkflowStep::factory(),
            'approver_id' => Employee::factory(),
            'action' => ApprovalAction::Approved,
            'comment' => null,
            'forwarded_to_id' => null,
            'decided_at' => now(),
        ];
    }
}
