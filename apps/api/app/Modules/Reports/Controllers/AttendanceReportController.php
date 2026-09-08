<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\AttendanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $service,
    ) {}

    /**
     * GET /api/admin/reports/attendance/monthly
     *   ?year=&month=&department_id=&format=json|csv
     *
     * Returns aggregated rows for the given month. Default JSON; `?format=csv`
     * streams a UTF-8 BOM'd CSV so Excel picks up the encoding correctly
     * (Arabic names read as Ø… otherwise on Windows).
     */
    public function monthly(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'department_id' => 'nullable|integer|exists:departments,id',
            'format' => 'nullable|in:json,csv',
        ]);

        $rows = $this->service->monthly(
            (int) $validated['year'],
            (int) $validated['month'],
            isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        );

        if (($validated['format'] ?? 'json') === 'csv') {
            return $this->csv($rows, "attendance-{$validated['year']}-{$validated['month']}.csv");
        }

        return response()->json(['data' => $rows]);
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
}
