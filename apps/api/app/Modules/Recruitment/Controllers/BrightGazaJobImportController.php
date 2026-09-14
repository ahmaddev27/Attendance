<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Integrations\BrightGaza\BrightGazaUnavailableException;
use App\Modules\Recruitment\Services\BrightGazaJobImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrightGazaJobImportController extends Controller
{
    public function __construct(
        private readonly BrightGazaJobImportService $imports,
    ) {}

    /**
     * `POST /api/recruitment/brightgaza/jobs/import` — the "pull from
     * BrightGaza" button on the jobs list.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $summary = $this->imports->import($request->user());
        } catch (BrightGazaUnavailableException $e) {
            Log::warning('BrightGaza job import failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'تعذر الوصول إلى BrightGaza الآن، حاول مرة أخرى بعد قليل.',
            ], 502);
        }

        return response()->json([
            'message' => sprintf(
                'تم سحب %d وظيفة من BrightGaza: %d جديدة، %d محدّثة.',
                $summary['fetched'],
                $summary['created'],
                $summary['updated'],
            ),
            'data' => $summary,
        ]);
    }
}
