<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentPipeline;
use App\Modules\Recruitment\Repositories\RecruitmentPipelineRepository;
use App\Modules\Recruitment\Requests\StoreRecruitmentPipelineRequest;
use App\Modules\Recruitment\Requests\UpdateRecruitmentPipelineRequest;
use App\Modules\Recruitment\Resources\RecruitmentPipelineResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pipeline admin — consumes the repository directly (no dedicated
 * service) since the write paths here are thin CRUD plus a single
 * "at most one default active pipeline" invariant enforced inline in a
 * transaction.
 */
class RecruitmentPipelineController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly RecruitmentPipelineRepository $pipelines,
    ) {}

    public function index(HttpRequest $request): AnonymousResourceCollection
    {
        $filters = [];

        if ($request->boolean('active_only')) {
            $filters['active_only'] = true;
        }

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return RecruitmentPipelineResource::collection($this->pipelines->paginate($filters, $perPage));
    }

    public function show(RecruitmentPipeline $pipeline): RecruitmentPipelineResource
    {
        return new RecruitmentPipelineResource($this->pipelines->findOrFail($pipeline->id));
    }

    public function store(StoreRecruitmentPipelineRequest $request): JsonResponse
    {
        $data = $request->validated();

        $pipeline = DB::transaction(function () use ($data) {
            $this->clearOtherDefaults(null, (bool) ($data['is_default'] ?? false));

            return $this->pipelines->create($data);
        });

        return (new RecruitmentPipelineResource($pipeline))->response()->setStatusCode(201);
    }

    public function update(UpdateRecruitmentPipelineRequest $request, RecruitmentPipeline $pipeline): RecruitmentPipelineResource
    {
        $data = $request->validated();

        $updated = DB::transaction(function () use ($pipeline, $data) {
            if (array_key_exists('is_default', $data)) {
                $this->clearOtherDefaults($pipeline->id, (bool) $data['is_default']);
            }

            return $this->pipelines->update($pipeline, $data);
        });

        return new RecruitmentPipelineResource($updated);
    }

    /**
     * A pipeline cannot be deleted while any JobRequirement still
     * references it — the FK trail would rot. Surface a 422 with a
     * clear message rather than letting the DB throw a constraint
     * error the FE has to guess at.
     */
    public function destroy(RecruitmentPipeline $pipeline): JsonResponse
    {
        if ($pipeline->jobs()->exists()) {
            throw ValidationException::withMessages([
                'pipeline' => 'لا يمكن حذف مسار مرتبط بوظائف قائمة.',
            ]);
        }

        DB::transaction(function () use ($pipeline) {
            $pipeline->stages()->delete();
            $pipeline->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Zeroes out `is_default` on every pipeline OTHER than the one
     * being marked default. Called inside the caller's transaction so
     * the "exactly one default" invariant is atomic. A no-op when the
     * incoming flag is false.
     */
    private function clearOtherDefaults(?int $currentId, bool $wantsDefault): void
    {
        if (! $wantsDefault) {
            return;
        }

        $query = RecruitmentPipeline::query()->where('is_default', true);

        if ($currentId !== null) {
            $query->where('id', '!=', $currentId);
        }

        $query->update(['is_default' => false]);
    }
}
