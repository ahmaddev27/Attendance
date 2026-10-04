<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Exports\RequestsExcelExport;
use App\Modules\Reports\Exports\RequestsPdfView;
use App\Modules\Reports\Requests\ExportRequestReportRequest;
use App\Modules\Reports\Services\RequestReportService;
use App\Shared\Support\CsvStream;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestReportController extends Controller
{
    public function __construct(
        private readonly RequestReportService $reports,
        private readonly CsvStream $csv,
    ) {}

    /**
     * GET /api/admin/reports/requests/export?format=csv|xlsx|pdf|json
     *
     * Defaults to csv, not json: this endpoint shipped CSV-only and existing
     * callers send no `format`.
     */
    public function export(ExportRequestReportRequest $request): JsonResponse|StreamedResponse|BinaryFileResponse|Response
    {
        $filters = $request->validated();
        $basename = $this->reports->exportBasename();

        return match ($filters['format'] ?? 'csv') {
            'xlsx' => Excel::download(
                new RequestsExcelExport(RequestReportService::EXPORT_HEADER, $this->reports->documentRows($filters)),
                "{$basename}.xlsx",
                ExcelWriter::XLSX,
            ),
            'pdf' => $this->pdf($filters, "{$basename}.pdf"),
            'json' => response()->json([
                'header' => RequestReportService::EXPORT_HEADER,
                'data' => $this->reports->documentRows($filters)->all(),
            ]),
            default => $this->csv->download(
                "{$basename}.csv",
                RequestReportService::EXPORT_HEADER,
                $this->reports->exportRows($filters),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function pdf(array $filters, string $filename): Response
    {
        $view = new RequestsPdfView(
            RequestReportService::EXPORT_HEADER,
            $this->reports->documentRows($filters),
            now()->format('Y-m-d H:i'),
        );

        return Pdf::loadView('exports.requests', ['view' => $view])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
