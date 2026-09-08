<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Search\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const MAX_LIMIT_PER_TYPE = 20;

    public function __construct(
        private readonly GlobalSearchService $globalSearch,
    ) {}

    /**
     * GET /api/search?q=...&limit=5
     *
     * Cross-index search. Every authenticated user may hit this — the
     * result set intentionally does NOT apply per-row visibility filters
     * (leaves/requests may leak titles across teams). Row-level scoping
     * is planned for a follow-up milestone.
     */
    public function query(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');
        $limit = (int) $request->integer('limit', GlobalSearchService::DEFAULT_LIMIT_PER_TYPE);
        $limit = max(1, min($limit, self::MAX_LIMIT_PER_TYPE));

        return response()->json([
            'data' => $this->globalSearch->query($term, $limit),
        ]);
    }
}
