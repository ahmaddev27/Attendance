<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\Lead;
use App\Models\RecruitmentCase;
use App\Models\Task;
use App\Shared\Enums\TaskEntityType;

/**
 * Stamps a human-readable `entity_label` ("J-2026-0045 — Senior Backend
 * Dev") onto tasks that point at a business entity, so TaskResource can
 * render a chip without a query per row. One lookup per entity type
 * present in the batch, regardless of page size.
 *
 * The label is a transient attribute on the model instance — it is
 * never written back. Only call this on read paths.
 */
final class TaskEntityLabeler
{
    /**
     * @param  iterable<int, Task>  $tasks
     */
    public function hydrate(iterable $tasks): void
    {
        /** @var array<string, list<Task>> $byType */
        $byType = [];

        foreach ($tasks as $task) {
            if ($task->entity_type instanceof TaskEntityType && $task->entity_id !== null) {
                $byType[$task->entity_type->value][] = $task;
            }
        }

        foreach ($byType as $type => $group) {
            $ids = array_values(array_unique(array_map(fn (Task $task) => (int) $task->entity_id, $group)));
            $labels = $this->labelsFor(TaskEntityType::from($type), $ids);

            foreach ($group as $task) {
                $task->setAttribute('entity_label', $labels[(int) $task->entity_id] ?? null);
            }
        }
    }

    /**
     * Trashed rows are included on purpose: a task that referenced a
     * lead which was later archived should still read sensibly.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function labelsFor(TaskEntityType $type, array $ids): array
    {
        return match ($type) {
            TaskEntityType::Lead => Lead::withTrashed()
                ->whereIn('id', $ids)
                ->get(['id', 'lead_number', 'company_name'])
                ->mapWithKeys(fn (Lead $lead) => [$lead->id => "{$lead->lead_number} — {$lead->company_name}"])
                ->all(),
            TaskEntityType::Client => Client::withTrashed()
                ->whereIn('id', $ids)
                ->get(['id', 'client_number', 'company_name'])
                ->mapWithKeys(fn (Client $client) => [$client->id => "{$client->client_number} — {$client->company_name}"])
                ->all(),
            TaskEntityType::RecruitmentCase => RecruitmentCase::withTrashed()
                ->whereIn('id', $ids)
                ->get(['id', 'case_number', 'title'])
                ->mapWithKeys(fn (RecruitmentCase $case) => [$case->id => "{$case->case_number} — {$case->title}"])
                ->all(),
            TaskEntityType::JobRequirement => JobRequirement::withTrashed()
                ->whereIn('id', $ids)
                ->get(['id', 'job_number', 'title'])
                ->mapWithKeys(fn (JobRequirement $job) => [$job->id => "{$job->job_number} — {$job->title}"])
                ->all(),
        };
    }
}
