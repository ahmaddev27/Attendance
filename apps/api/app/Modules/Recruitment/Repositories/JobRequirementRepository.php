<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Shared\Enums\JobRequirementStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JobRequirementRepository
{
    /**
     * @var list<string>
     */
    private const WITH = [
        'recruitmentCase.client',
        'pipeline',
        'currentStage',
        'owner',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = JobRequirement::query()->with(self::WITH);

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function paginateForCase(RecruitmentCase $case, int $perPage): LengthAwarePaginator
    {
        return JobRequirement::query()
            ->with(self::WITH)
            ->where('recruitment_case_id', $case->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): JobRequirement
    {
        return JobRequirement::query()->with(self::WITH)->findOrFail($id);
    }

    public function findForUpdate(int $id): JobRequirement
    {
        return JobRequirement::query()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): JobRequirement
    {
        return JobRequirement::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JobRequirement $job, array $data): JobRequirement
    {
        $job->fill($data)->save();

        return $job->fresh(self::WITH) ?? $job;
    }

    public function highestSequenceForYear(int $year): int
    {
        $prefix = "J-{$year}-";

        // withTrashed: the *_number unique index still holds soft-deleted rows,
        // so skipping them would hand out a number that is already taken.
        $latest = JobRequirement::withTrashed()
            ->where('job_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('job_number');

        if ($latest === null) {
            return 0;
        }

        return (int) mb_substr((string) $latest, mb_strlen($prefix));
    }

    /**
     * Jobs whose current stage has an SLA and whose stage_entered_at is
     * older than (now - sla_hours) — the input the SLA scanner uses to
     * emit "stage overdue" notifications.
     *
     * @return Collection<int, JobRequirement>
     */
    public function stagesBreachingSla(Carbon $now): Collection
    {
        return JobRequirement::query()
            ->with([...self::WITH, 'owner'])
            ->open()
            ->whereHas('currentStage', fn (Builder $stage) => $stage->whereNotNull('sla_hours'))
            ->get()
            ->filter(function (JobRequirement $job) use ($now): bool {
                $sla = $job->currentStage?->sla_hours;

                if ($sla === null || $sla <= 0) {
                    return false;
                }

                $enteredAt = $job->stage_entered_at;

                if ($enteredAt === null) {
                    return false;
                }

                return $enteredAt->clone()->addHours((int) $sla)->lessThanOrEqualTo($now);
            })
            ->values();
    }

    /**
     * @param  Builder<JobRequirement>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach ([
            'recruitment_case_id',
            'pipeline_id',
            'current_stage_id',
            'owner_id',
            'status',
            'employment_type',
            'work_mode',
        ] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('open_only', $filters) && $filters['open_only']) {
            $query->whereIn('status', [
                JobRequirementStatus::Draft->value,
                JobRequirementStatus::Active->value,
                JobRequirementStatus::OnHold->value,
            ]);
        }

        if (! empty($filters['client_id'])) {
            $clientId = $filters['client_id'];
            $query->whereHas('recruitmentCase', fn (Builder $c) => $c->where('client_id', $clientId));
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('job_number', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Pipeline tasks are the system-generated ones (no creator). Leaving a
     * stage, or cancelling the job, finishes whichever are still open.
     */
    public function closeOpenPipelineTasks(JobRequirement $job): void
    {
        $doneStatusId = TaskStatus::query()
            ->where('is_done_state', true)
            ->orderBy('sort_order')
            ->value('id');

        $attributes = ['completed_at' => now()];

        if ($doneStatusId !== null) {
            $attributes['status_id'] = $doneStatusId;
        }

        $job->tasks()
            ->whereNull('created_by')
            ->whereNull('completed_at')
            ->get()
            ->each(fn (Task $task) => $task->update($attributes));
    }

    /**
     * Users behind the open pipeline tasks of a job, i.e. the people
     * currently responsible for its stage.
     *
     * @return list<int>
     */
    public function openPipelineTaskAssigneeUserIds(JobRequirement $job): array
    {
        $assigneeIds = $job->tasks()
            ->whereNull('created_by')
            ->whereNull('completed_at')
            ->whereNotNull('assigned_to')
            ->pluck('assigned_to');

        if ($assigneeIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('employee_id', $assigneeIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Includes soft-deleted rows: a job deleted in TAQAT still owns its
     * external reference, so a later pull must see it rather than recreate it.
     */
    public function findByExternalReference(string $source, string $externalId): ?JobRequirement
    {
        return JobRequirement::withTrashed()
            ->where('external_source', $source)
            ->where('external_id', $externalId)
            ->first();
    }

    /**
     * Flags every job from $source missing from the latest pull.
     *
     * @param  list<string>  $listedIds
     */
    public function markExternalNotListed(string $source, array $listedIds, string $status): int
    {
        return JobRequirement::query()
            ->where('external_source', $source)
            ->whereNotIn('external_id', $listedIds)
            ->where(fn (Builder $query) => $query->whereNull('external_status')->orWhere('external_status', '!=', $status))
            ->update(['external_status' => $status, 'updated_at' => now()]);
    }
}
