<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Tasks\Requests\EmployeeTasksReportRequest;
use App\Modules\Tasks\Services\EmployeeTasksReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

class EmployeeTasksReportController extends Controller
{
    public function __construct(private readonly EmployeeTasksReportService $reports) {}

    /** GET /api/admin/employees/{employee}/tasks-report */
    public function show(EmployeeTasksReportRequest $request, Employee $employee): JsonResponse
    {
        return response()->json($this->reports->report($employee, $request->year(), $request->month()));
    }

    /** GET /api/admin/employees/{employee}/tasks-report/pdf */
    public function pdf(EmployeeTasksReportRequest $request, Employee $employee): Response
    {
        try {
            $report = $this->reports->report($employee, $request->year(), $request->month());
            $filename = "employee-{$employee->employee_number}-tasks-{$report['year']}.pdf";

            // mPDF (not dompdf): dompdf's bundled DejaVu Sans does not
            // shape Arabic glyphs — connected letters render as isolated
            // forms and the whole line reads backwards. mPDF ships Arabic
            // -capable fonts (xbriyaz / sun / amiri bundled under
            // mpdf/mpdf's resources) and does the OpenType shaping
            // natively when autoScriptToLang + autoLangToFont are on.
            $html = view('reports.employee-tasks', [
                'report' => $report,
                'generatedAt' => now('Asia/Gaza')->format('Y-m-d H:i'),
            ])->render();

            $tmpDir = storage_path('app/mpdf-tmp');
            if (! is_dir($tmpDir)) {
                mkdir($tmpDir, 0775, true);
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 12,
                'margin_bottom' => 12,
                'margin_left' => 10,
                'margin_right' => 10,
                // dejavusans covers Latin + Arabic basic forms; with
                // autoScriptToLang + autoLangToFont, mPDF transparently
                // substitutes a shaping-capable Arabic font (xbriyaz or
                // amiri from its bundled set) whenever it meets Arabic
                // script in the HTML.
                'default_font' => 'dejavusans',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'tempDir' => $tmpDir,
            ]);
            $mpdf->SetDirectionality('rtl');
            $mpdf->WriteHTML($html);

            return new Response(
                $mpdf->Output('', 'S'),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                ],
            );
        } catch (\Throwable $e) {
            // mPDF failures (bad font config, chroot block, memory limit)
            // used to surface as a generic 500 — admins only saw
            // "تعذر التحميل". Log the full stacktrace once, then let
            // Laravel's handler return 500 (behaviour unchanged for callers).
            Log::error('EmployeeTasksReport PDF generation failed', [
                'employee_id' => $employee->id,
                'year' => $request->year(),
                'month' => $request->month(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            throw $e;
        }
    }
}
