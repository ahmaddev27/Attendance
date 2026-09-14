<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\JobRequirement;
use App\Models\RecruitmentCase;
use App\Models\RecruitmentPipelineStage;
use App\Models\User;
use App\Modules\Recruitment\Events\JobRequirementStageAdvanced;
use App\Modules\Recruitment\Events\JobRequirementSubmitted;
use App\Modules\Recruitment\Repositories\JobRequirementRepository;
use App\Modules\Recruitment\Repositories\RecruitmentPipelineRepository;
use App\Shared\Enums\JobRequirementStatus;
use App\Shared\Enums\StageOwnerRule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the JobRequirement lifecycle end-to-end:
 *   create() drops a new job at the first pipeline stage,
 *   update() covers the free-form field edits (never the stage!),
 *   advanceStage() is the ONE transition point — it validates the
 *   requires_fields gate, stamps stage_entered_at, and fires
 *   JobRequirementStageAdvanced so PipelineTaskGenerator can spawn
 *   the next-stage task on the resolved owner.
 */
class JobRequirementService
{
    public function __construct(
        private readonly JobRequirementRepository $jobs,
        private readonly RecruitmentPipelineRepository $pipelines,
        private readonly RecruitmentNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->jobs->paginate($filters, $perPage);
    }

    public function paginateForCase(RecruitmentCase $case, int $perPage = 25): LengthAwarePaginator
    {
        return $this->jobs->paginateForCase($case, $perPage);
    }

    public function find(int $id): JobRequirement
    {
        return $this->jobs->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): JobRequirement
    {
        $pipeline = $this->resolvePipeline($data['pipeline_id'] ?? null);
        $firstStage = $pipeline->firstStage();

        if ($firstStage === null) {
            throw ValidationException::withMessages([
                'pipeline_id' => 'المسار المختار لا يحتوي على أي مرحلة — أضف مرحلة قبل إنشاء الوظائف.',
            ]);
        }

        $job = DB::transaction(function () use ($data, $pipeline, $firstStage) {
            $data['job_number'] = $this->numbers->nextJobNumber();
            $data['pipeline_id'] = $pipeline->id;
            $data['current_stage_id'] = $firstStage->id;
            $data['stage_entered_at'] = now();

            // status defaults to Draft in the model; explicit values are
            // honored (e.g. Case seeded via Convert flow starts Active).
            $data['status'] = $data['status'] ?? JobRequirementStatus::Active->value;

            return $this->jobs->create($data);
        });

        JobRequirementSubmitted::dispatch($job, $actor);

        // A non-terminal first stage with auto_generate_task = true
        // triggers the initial "publish" task (or whatever the pipeline
        // starts on). Fire the same event advanceStage fires so the
        // generator only has one code path to listen on.
        JobRequirementStageAdvanced::dispatch($job, null, $firstStage);

        return $job;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(JobRequirement $job, array $data): JobRequirement
    {
        if (in_array($job->status, [JobRequirementStatus::Filled, JobRequirementStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تعديل وظيفة بعد إغلاقها أو إلغائها.',
            ]);
        }

        return $this->jobs->update($job, $data);
    }

    /**
     * @param  array<string, mixed>  $payload  target_stage_id / fields / handoff_note
     *
     * @throws AuthorizationException when $actor is not responsible for the job right now
     */
    public function advanceStage(JobRequirement $job, array $payload, User $actor): JobRequirement
    {
        $this->assertStillOpen($job);

        $fromStage = $job->currentStage;

        if ($fromStage === null) {
            throw ValidationException::withMessages([
                'current_stage' => 'الوظيفة بدون مرحلة حالية — تعذّر التحقق من التقدم.',
            ]);
        }

        if ($fromStage->is_terminal) {
            throw ValidationException::withMessages([
                'current_stage' => 'الوظيفة في مرحلة نهائية ولا يمكن نقلها.',
            ]);
        }

        $this->assertCanAdvance($job, $fromStage, $actor);

        // The target: caller-specified (must be in the same pipeline
        // AND downstream), or the next stage in display_order if
        // unspecified — the common happy path.
        $target = isset($payload['target_stage_id'])
            ? $this->pipelines->stageOrFail($job->pipeline_id, (int) $payload['target_stage_id'])
            : $this->pipelines->nextStage($fromStage);

        if ($target === null) {
            throw ValidationException::withMessages([
                'target_stage_id' => 'لا توجد مرحلة تالية — أضف مرحلة نهائية أو حدد الوظيفة كمكتملة.',
            ]);
        }

        if ($target->pipeline_id !== $job->pipeline_id) {
            throw ValidationException::withMessages([
                'target_stage_id' => 'المرحلة المستهدفة لا تنتمي إلى نفس مسار الوظيفة.',
            ]);
        }

        if ($target->display_order <= $fromStage->display_order) {
            throw ValidationException::withMessages([
                'target_stage_id' => 'لا يمكن الرجوع لمراحل سابقة — استخدم مرحلة لاحقة أو نهائية.',
            ]);
        }

        // Gate: every field the CURRENT stage listed under
        // requires_fields must be either non-null on the job already,
        // or provided in the payload's `fields` bag. This is what turns
        // "publish stage requires publication_url" into an enforceable
        // hand-off contract.
        $fieldPatch = $payload['fields'] ?? [];
        $this->assertRequiredFields($job, $fromStage, is_array($fieldPatch) ? $fieldPatch : []);

        $updated = DB::transaction(function () use ($job, $target, $fieldPatch, $fromStage) {
            $locked = $this->jobs->findForUpdate($job->id);

            // The checks above ran on the caller's copy of the job. A second
            // request (a double click) may have moved or closed it since, so
            // stage and status are checked again under the row lock.
            if ((int) $locked->current_stage_id !== (int) $fromStage->id) {
                throw ValidationException::withMessages([
                    'current_stage' => 'تم نقل هذه الوظيفة إلى مرحلة أخرى للتو — حدّث الصفحة وحاول مجدداً.',
                ]);
            }

            $this->assertStillOpen($locked);

            $patch = is_array($fieldPatch) ? $fieldPatch : [];

            // Stamp side-effect fields ONLY when the stage we're leaving
            // is the one that owns them (e.g. publication_url is
            // populated when we leave 'publish'; published_at snaps to
            // now at that same instant).
            if ($fromStage->code === 'publish' && ! empty($patch['publication_url'])) {
                $patch['published_at'] = $patch['published_at'] ?? now();
            }

            $patch['current_stage_id'] = $target->id;
            $patch['stage_entered_at'] = now();

            if ($target->is_terminal) {
                $patch['completed_at'] = now();
                $patch['status'] = $target->code === 'hired'
                    ? JobRequirementStatus::Filled->value
                    : JobRequirementStatus::Cancelled->value;
            }

            $this->jobs->closeOpenPipelineTasks($locked);

            return $this->jobs->update($locked, $patch);
        });

        JobRequirementStageAdvanced::dispatch($updated, $fromStage, $target, $payload['handoff_note'] ?? null);

        return $updated;
    }

    public function cancel(JobRequirement $job): JobRequirement
    {
        if (in_array($job->status, [JobRequirementStatus::Filled, JobRequirementStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => 'الوظيفة مغلقة أصلاً.',
            ]);
        }

        return DB::transaction(function () use ($job) {
            $locked = $this->jobs->findForUpdate($job->id);
            $patch = [
                'status' => JobRequirementStatus::Cancelled->value,
                'completed_at' => now(),
            ];

            // Park the job on the pipeline's cancelled stage so the stage bar,
            // the funnel and the advance gate all agree that it is closed.
            $cancelledStage = $this->pipelines->terminalStage((int) $locked->pipeline_id, 'cancelled');

            if ($cancelledStage !== null) {
                $patch['current_stage_id'] = $cancelledStage->id;
                $patch['stage_entered_at'] = now();
            }

            $this->jobs->closeOpenPipelineTasks($locked);

            return $this->jobs->update($locked, $patch);
        });
    }

    public function delete(JobRequirement $job): void
    {
        $job->delete();
    }

    private function assertStillOpen(JobRequirement $job): void
    {
        if (in_array($job->status, [JobRequirementStatus::Filled, JobRequirementStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن نقل وظيفة مغلقة أو ملغاة.',
            ]);
        }
    }

    /**
     * The route admits anyone holding advance-job-stage; this narrows it to
     * the people responsible for the job right now: jobs managers, the job
     * and case owners, holders of the stage's owner permission, and the
     * assignees of its open pipeline tasks.
     *
     * @throws AuthorizationException
     */
    private function assertCanAdvance(JobRequirement $job, RecruitmentPipelineStage $stage, User $actor): void
    {
        $holdsStagePermission = $stage->owner_rule_type === StageOwnerRule::Role
            && ! empty($stage->owner_rule_value)
            && $actor->can($stage->owner_rule_value);

        if ($actor->can('manage-jobs')
            || (int) $actor->id === (int) $job->owner_id
            || (int) $actor->id === (int) $job->recruitmentCase?->owner_id
            || $holdsStagePermission
            || in_array((int) $actor->id, $this->jobs->openPipelineTaskAssigneeUserIds($job), true)) {
            return;
        }

        throw new AuthorizationException('لست مسؤولاً عن هذه المرحلة من الوظيفة، لذلك لا يمكنك نقلها.');
    }

    /**
     * Resolve the pipeline to attach a new job to. When the caller
     * didn't specify one, fall back to the seeded default. Missing both
     * is a config error worth surfacing loudly.
     */
    private function resolvePipeline(?int $pipelineId): \App\Models\RecruitmentPipeline
    {
        if ($pipelineId !== null) {
            return $this->pipelines->findOrFail($pipelineId);
        }

        $default = $this->pipelines->default();

        if ($default === null) {
            throw ValidationException::withMessages([
                'pipeline_id' => 'لا يوجد مسار افتراضي مفعّل — اطلب من الإدارة تفعيل مسار افتراضي.',
            ]);
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $fieldPatch
     */
    private function assertRequiredFields(JobRequirement $job, RecruitmentPipelineStage $stage, array $fieldPatch): void
    {
        $missing = [];

        foreach ($stage->requiredFields() as $fieldName) {
            $incoming = $fieldPatch[$fieldName] ?? null;
            $existing = $job->getAttribute($fieldName);

            $hasIncoming = $incoming !== null && $incoming !== '' && $incoming !== [];
            $hasExisting = $existing !== null && $existing !== '' && $existing !== [];

            if (! $hasIncoming && ! $hasExisting) {
                $missing[] = $fieldName;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'fields' => sprintf('الحقول التالية مطلوبة قبل الانتقال: %s', implode(', ', $missing)),
            ]);
        }
    }
}
