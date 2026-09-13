<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Requests\ExportLeaveReportRequest;
use App\Modules\Reports\Services\LeaveReportService;
use App\Shared\Support\CsvStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveReportController extends Controller
{
    public function __construct(
        private readonly LeaveReportService $reports,
        private readonly CsvStream $csv,
    ) {}

    public function export(ExportLeaveReportRequest $request): StreamedResponse
    {
        $filters = $request->validated();

        return $this->csv->download(
            $this->reports->exportFilename($filters),
            LeaveReportService::EXPORT_HEADER,
            $this->reports->exportRows($filters),
        );
    }
}
