<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Exports\AttendanceMonthlyExcelExport;
use App\Modules\Reports\Exports\AttendanceMonthlyPdfView;
use App\Modules\Reports\Services\AttendanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $service,
    ) {}

    /**
     * GET /api/admin/reports/attendance/monthly
     *   ?year=&month=&department_id=&format=json|csv|xlsx|pdf
     *
     * Returns aggregated rows for the given month. Default JSON; the file
     * formats attach a Content-Disposition so browsers download instead
     * of rendering inline.
     *
     * - csv:  UTF-8 BOM-prefixed stream so Excel picks the encoding up.
     * - xlsx: RTL sheet with a styled header, via maatwebsite/excel.
     * - pdf:  Blade → dompdf render; Arabic supported via DejaVu Sans
     *         (bundled) with an Amiri @font-face upgrade when the API
     *         host has internet egress.
     */
    public function monthly(Request $request): JsonResponse|StreamedResponse|BinaryFileResponse|Response
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'department_id' => 'nullable|integer|exists:departments,id',
            'format' => 'nullable|in:json,csv,xlsx,pdf',
        ]);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $departmentId = isset($validated['department_id']) ? (int) $validated['department_id'] : null;

        $rows = $this->service->monthly($year, $month, $departmentId);

        $filenameBase = sprintf('attendance-%04d-%02d', $year, $month);

        return match ($validated['format'] ?? 'json') {
            'csv' => $this->csv($rows, "{$filenameBase}.csv"),
            'xlsx' => $this->xlsx($rows, "{$filenameBase}.xlsx", $year, $month),
            'pdf' => $this->pdf($rows, "{$filenameBase}.pdf", $year, $month),
            default => response()->json(['data' => $rows]),
        };
    }

    private function csv(iterable $rows, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            // Excel-friendly UTF-8 BOM so Arabic columns render correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'رقم الموظف',
                'الاسم',
                'القسم',
                'أيام الحضور',
                'أيام التأخير',
                'أيام الغياب',
                'أيام الإجازة',
                'إجمالي الدقائق',
                'دقائق الوقت الإضافي',
                'دقائق التأخير',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['employee_number'],
                    $row['full_name'],
                    $row['department'] ?? '',
                    $row['present_days'],
                    $row['late_days'],
                    $row['absent_days'],
                    $row['leave_days'],
                    $row['total_minutes'],
                    $row['overtime_minutes'],
                    $row['late_minutes'],
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function xlsx(Collection $rows, string $filename, int $year, int $month): BinaryFileResponse
    {
        return Excel::download(
            new AttendanceMonthlyExcelExport($rows, $year, $month),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function pdf(Collection $rows, string $filename, int $year, int $month): Response
    {
        $view = new AttendanceMonthlyPdfView(
            rows: $rows,
            year: $year,
            month: $month,
            generatedAt: now()->format('Y-m-d H:i'),
        );

        return Pdf::loadView('exports.attendance-monthly', ['view' => $view])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
