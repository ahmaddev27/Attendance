<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Exports\LeavesExcelExport;
use App\Modules\Reports\Exports\LeavesPdfView;
use App\Modules\Reports\Requests\ExportLeaveReportRequest;
use App\Modules\Reports\Services\LeaveReportService;
use App\Shared\Support\CsvStream;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveReportController extends Controller
{
    public function __construct(
        private readonly LeaveReportService $reports,
        private readonly CsvStream $csv,
    ) {}

    /**
     * GET /api/admin/reports/leaves/export?format=csv|xlsx|pdf|json
     *
     * Defaults to csv, not json: this endpoint shipped CSV-only and existing
     * callers send no `format`.
     */
    public function export(ExportLeaveReportRequest $request): JsonResponse|StreamedResponse|BinaryFileResponse|Response
    {
        $filters = $request->validated();
        $basename = $this->reports->exportBasename($filters);

        return match ($filters['format'] ?? 'csv') {
            'xlsx' => Excel::download(
                new LeavesExcelExport(LeaveReportService::EXPORT_HEADER, $this->reports->documentRows($filters)),
                "{$basename}.xlsx",
                ExcelWriter::XLSX,
            ),
            'pdf' => $this->pdf($filters, "{$basename}.pdf"),
            'json' => response()->json([
                'header' => LeaveReportService::EXPORT_HEADER,
                'data' => $this->reports->documentRows($filters)->all(),
            ]),
            default => $this->csv->download(
                "{$basename}.csv",
                LeaveReportService::EXPORT_HEADER,
                $this->reports->exportRows($filters),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function pdf(array $filters, string $filename): Response
    {
        $view = new LeavesPdfView(
            LeaveReportService::EXPORT_HEADER,
            $this->reports->documentRows($filters),
            now()->format('Y-m-d H:i'),
        );

        // Landscape: 12 columns do not fit a portrait A4 legibly.
        return Pdf::loadView('exports.leaves', ['view' => $view])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}
