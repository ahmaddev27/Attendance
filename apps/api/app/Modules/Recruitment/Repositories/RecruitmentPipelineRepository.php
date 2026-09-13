<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Repositories;

use App\Models\RecruitmentPipeline;
use App\Models\RecruitmentPipelineStage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RecruitmentPipelineRepository
{
    /**
     * @var list<string>
     */
    private const WITH = ['stages'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = RecruitmentPipeline::query()->with(self::WITH);

        if (array_key_exists('active_only', $filters) && $filters['active_only']) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * @return Collection<int, RecruitmentPipeline>
     */
    public function all(): Collection
    {
        return RecruitmentPipeline::query()->with(self::WITH)->orderBy('name')->get();
    }

    public function findOrFail(int $id): RecruitmentPipeline
    {
        return RecruitmentPipeline::query()->with(self::WITH)->findOrFail($id);
    }

    public function default(): ?RecruitmentPipeline
    {
        return RecruitmentPipeline::query()
            ->with(self::WITH)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RecruitmentPipeline
    {
        return RecruitmentPipeline::query()->create($data)->load(self::WITH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RecruitmentPipeline $pipeline, array $data): RecruitmentPipeline
    {
        $pipeline->fill($data)->save();

        return $pipeline->fresh(self::WITH) ?? $pipeline;
    }

    public function stageOrFail(int $pipelineId, int $stageId): RecruitmentPipelineStage
    {
        return RecruitmentPipelineStage::query()
            ->where('pipeline_id', $pipelineId)
            ->where('id', $stageId)
            ->firstOrFail();
    }

    /**
     * The stage immediately after $current in the same pipeline, or null
     * if $current is the last (terminal) stage.
     */
    public function nextStage(RecruitmentPipelineStage $current): ?RecruitmentPipelineStage
    {
        return RecruitmentPipelineStage::query()
            ->where('pipeline_id', $current->pipeline_id)
            ->where('display_order', '>', $current->display_order)
            ->orderBy('display_order')
            ->first();
    }

    /**
     * The stage immediately before $current — used to resolve the
     * PreviousStageOwner rule when the transition itself is inside a
     * generator that no longer has the old task in hand.
     */
    public function previousStage(RecruitmentPipelineStage $current): ?RecruitmentPipelineStage
    {
        return RecruitmentPipelineStage::query()
            ->where('pipeline_id', $current->pipeline_id)
            ->where('display_order', '<', $current->display_order)
            ->orderByDesc('display_order')
            ->first();
    }
}
