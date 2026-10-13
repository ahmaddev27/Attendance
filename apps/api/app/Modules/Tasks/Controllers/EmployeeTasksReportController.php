<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Tasks\Requests\EmployeeTasksReportRequest;
use App\Modules\Tasks\Services\EmployeeTasksReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
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
        $report = $this->reports->report($employee, $request->year(), $request->month());
        $filename = "employee-{$employee->employee_number}-tasks-{$report['year']}.pdf";

        return Pdf::loadView('reports.employee-tasks', [
            'report' => $report,
            'generatedAt' => now('Asia/Gaza')->format('Y-m-d H:i'),
        ])->setPaper('a4')->download($filename);
    }
}
