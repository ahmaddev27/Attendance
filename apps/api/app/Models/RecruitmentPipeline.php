<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable template that groups ordered stages a JobRequirement
 * moves through. Multiple pipelines can coexist (e.g. "Standard",
 * "Fast-track Contract") so different job categories can follow
 * different hand-off flows without conditionals in the service layer.
 *
 * `is_default` marks the pipeline JobRequirements are attached to
 * when none is explicitly chosen — softly enforced (single row) by
 * RecruitmentPipelineService rather than a partial unique index.
 */
class RecruitmentPipeline extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RecruitmentPipelineStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(RecruitmentPipelineStage::class, 'pipeline_id')
            ->orderBy('display_order');
    }

    /**
     * @return HasMany<JobRequirement, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(JobRequirement::class, 'pipeline_id');
    }

    public function firstStage(): ?RecruitmentPipelineStage
    {
        return $this->stages()->first();
    }
}
