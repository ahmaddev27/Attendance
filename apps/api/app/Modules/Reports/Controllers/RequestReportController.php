<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Requests\ExportRequestReportRequest;
use App\Modules\Reports\Services\RequestReportService;
use App\Shared\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestReportController extends Controller
{
    public function __construct(
        private readonly RequestReportService $reports,
        private readonly CsvStream $csv,
    ) {}

    public function export(ExportRequestReportRequest $request): StreamedResponse
    {
        return $this->csv->download(
            $this->reports->exportFilename(),
            RequestReportService::EXPORT_HEADER,
            $this->reports->exportRows($request->validated()),
        );
    }
}
