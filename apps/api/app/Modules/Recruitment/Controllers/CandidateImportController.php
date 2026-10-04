<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CandidateImportJob;
use App\Models\JobRequirement;
use App\Modules\Recruitment\Requests\DryRunCandidateImportRequest;
use App\Modules\Recruitment\Requests\StoreCandidateImportRequest;
use App\Modules\Recruitment\Resources\CandidateImportJobResource;
use App\Modules\Recruitment\Services\CandidateImportService;
use App\Shared\Support\CsvStream;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * HTTP surface for the CSV / XLSX bulk Candidate importer. Thin by
 * design — the parsing + dedup + queue dispatch logic lives on
 * CandidateImportService. Every action here is already behind the
 * `manage-candidates` middleware on the route; the Form Requests
 * re-check the permission as defence in depth.
 */
class CandidateImportController extends Controller
{
    public function __construct(
        private readonly CandidateImportService $importer,
        private readonly CsvStream $csv,
    ) {}

    /**
     * Streams a bare CSV template (header row only) the admin can hand
     * off to the client / their recruitment partner. BOM + formula
     * escaping are handled by CsvStream.
     */
    public function template(JobRequirement $job): StreamedResponse
    {
        return $this->csv->download(
            "candidates-import-template-job-{$job->id}.csv",
            CandidateImportService::TEMPLATE_HEADERS,
            [],
        );
    }

    /**
     * Pure validation pass — no row is persisted. The dry-run response
     * is the shape the UI's confirm step reads: parsed count, errors,
     * and a 10-row preview after normalisation.
     *
     * @return JsonResponse
     */
    public function dryRun(DryRunCandidateImportRequest $request, JobRequirement $job): JsonResponse
    {
        $file = $request->file('file');
        $result = $this->importer->parse($file);

        return response()->json([
            'data' => [
                'job_requirement_id' => $job->id,
                'parsed' => $result['rows_parsed'],
                'errors' => $result['errors'],
                'preview' => $result['preview'],
            ],
        ]);
    }

    /**
     * Stores the uploaded file + dispatches the worker. The tracker
     * row's id is the handle the client polls on.
     */
    public function store(StoreCandidateImportRequest $request, JobRequirement $job): JsonResponse
    {
        /** @var \App\Models\User $actor */
        $actor = $request->user();

        $importJob = $this->importer->queue($job, $request->file('file'), $actor);

        return CandidateImportJobResource::make($importJob)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Poll endpoint for the uploader. 404 when the row belongs to a
     * different job — a worker scoped to Job A must never leak status
     * for an import on Job B through URL guessing.
     */
    public function status(JobRequirement $job, CandidateImportJob $jobRun): CandidateImportJobResource
    {
        if ($jobRun->job_requirement_id !== $job->id) {
            throw new NotFoundHttpException();
        }

        return CandidateImportJobResource::make($jobRun);
    }
}
