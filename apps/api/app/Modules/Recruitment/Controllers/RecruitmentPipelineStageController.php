<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JobRequirement;
use App\Models\RecruitmentPipeline;
use App\Models\RecruitmentPipelineStage;
use App\Modules\Recruitment\Requests\ReorderPipelineStagesRequest;
use App\Modules\Recruitment\Requests\StoreRecruitmentPipelineStageRequest;
use App\Modules\Recruitment\Requests\UpdateRecruitmentPipelineStageRequest;
use App\Modules\Recruitment\Resources\RecruitmentPipelineStageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nested under /recruitment-pipelines/{pipeline}/stages. Both write
 * paths (store, update) verify the target stage belongs to the parent
 * pipeline — the implicit binding alone would resolve any stage id.
 */
class RecruitmentPipelineStageController extends Controller
{
    /**
     * Store/Update requests cap display_order at 32767, so shifting by this
     * amount keeps every parked value inside UNSIGNED SMALLINT (65535) and
     * strictly above any final position the reorder will assign.
     */
    private const REORDER_PARK_OFFSET = 32768;

    public function store(StoreRecruitmentPipelineStageRequest $request, RecruitmentPipeline $pipeline): JsonResponse
    {
        $data = $request->validated();

        $stage = DB::transaction(function () use ($pipeline, $data) {
            // Default display_order to the tail of the pipeline so an
            // admin adding a stage from the UI doesn't have to compute
            // the next slot themselves.
            $data['display_order'] = $data['display_order']
                ?? ((int) $pipeline->stages()->max('display_order') + 1);

            $data['pipeline_id'] = $pipeline->id;

            return RecruitmentPipelineStage::query()->create($data);
        });

        return (new RecruitmentPipelineStageResource($stage))->response()->setStatusCode(201);
    }

    public function update(UpdateRecruitmentPipelineStageRequest $request, RecruitmentPipeline $pipeline, RecruitmentPipelineStage $stage): RecruitmentPipelineStageResource
    {
        $this->guardOwnership($pipeline, $stage);

        $stage->fill($request->validated())->save();

        return new RecruitmentPipelineStageResource($stage->fresh() ?? $stage);
    }

    public function destroy(RecruitmentPipeline $pipeline, RecruitmentPipelineStage $stage): JsonResponse
    {
        $this->guardOwnership($pipeline, $stage);

        // A stage cannot be dropped while a live job sits on it —
        // otherwise the FK trail on job_requirements.current_stage_id
        // would rot. Consistent with the "prevent delete stage in use"
        // test case from PipelineAdminTest.
        if (JobRequirement::query()->where('current_stage_id', $stage->id)->exists()) {
            throw ValidationException::withMessages([
                'stage' => 'لا يمكن حذف مرحلة توجد بها وظائف نشطة.',
            ]);
        }

        $stage->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk reorder: replay the caller-supplied id list into
     * display_order starting at 1. Two-pass update (negative sentinel
     * first, then final values) side-steps the (pipeline_id,
     * display_order) uniqueness constraint during the shuffle.
     */
    public function reorder(ReorderPipelineStagesRequest $request, RecruitmentPipeline $pipeline): AnonymousResourceCollection
    {
        /** @var list<int> $stageIds */
        $stageIds = array_map('intval', $request->validated('stage_ids'));

        $existing = RecruitmentPipelineStage::query()
            ->where('pipeline_id', $pipeline->id)
            ->pluck('id')
            ->all();

        // Every id must belong to this pipeline AND every stage in the
        // pipeline must appear in the incoming list — otherwise the
        // resulting ordering leaves gaps and the (pipeline, order)
        // unique index throws later.
        if (
            count($stageIds) !== count($existing)
            || array_diff($stageIds, $existing) !== []
        ) {
            throw ValidationException::withMessages([
                'stage_ids' => 'قائمة المراحل يجب أن تحتوي على كل مراحل المسار بدون إضافات.',
            ]);
        }

        DB::transaction(function () use ($stageIds, $pipeline) {
            // Park every row above the reachable range first. display_order is
            // UNSIGNED SMALLINT with a (pipeline_id, display_order) unique index,
            // so a negative sentinel overflows on MySQL and any in-range
            // temporary value can collide mid-shuffle.
            RecruitmentPipelineStage::query()
                ->where('pipeline_id', $pipeline->id)
                ->update(['display_order' => DB::raw('display_order + ' . self::REORDER_PARK_OFFSET)]);

            foreach ($stageIds as $index => $stageId) {
                RecruitmentPipelineStage::query()
                    ->where('pipeline_id', $pipeline->id)
                    ->where('id', $stageId)
                    ->update(['display_order' => $index + 1]);
            }
        });

        $stages = RecruitmentPipelineStage::query()
            ->where('pipeline_id', $pipeline->id)
            ->orderBy('display_order')
            ->get();

        return RecruitmentPipelineStageResource::collection($stages);
    }

    private function guardOwnership(RecruitmentPipeline $pipeline, RecruitmentPipelineStage $stage): void
    {
        if ($stage->pipeline_id !== $pipeline->id) {
            abort(404);
        }
    }
}
