<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Tasks\Requests\EmployeeTasksReportRequest;
use App\Modules\Tasks\Services\EmployeeTasksReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
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

            return Pdf::loadView('reports.employee-tasks', [
                'report' => $report,
                'generatedAt' => now('Asia/Gaza')->format('Y-m-d H:i'),
            ])->setPaper('a4')->download($filename);
        } catch (\Throwable $e) {
            // Dompdf failures (font fetch stall, inline-PHP block,
            // undefined-array-key warning promoted to exception) used to
            // surface as a generic 500 — admins only saw "تعذر التحميل".
            // Log the full stacktrace once, then let Laravel's handler
            // return 500 (behaviour unchanged for callers).
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
