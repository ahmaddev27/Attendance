<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Modules\Attendance\Repositories\AttendanceRepository;
use App\Modules\Attendance\Requests\UpdateAttendanceRequest;
use App\Modules\Attendance\Resources\AttendanceResource;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Attendance\Services\AttendanceStatsService;
use App\Modules\Attendance\Services\WorkingHoursCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceRepository $attendances,
        private readonly WorkingHoursCalculator $calculator,
        private readonly AttendanceStatsService $stats,
        private readonly AttendanceService $attendanceService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $attendances = $this->attendances->paginate(
            $request->only(['employee_id', 'status', 'date_from', 'date_to', 'company_id']),
            (int) $request->integer('per_page', 15),
        );

        return AttendanceResource::collection($attendances);
    }

    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $summary = $this->stats->summarise(
            $request->only(['employee_id', 'status', 'date_from', 'date_to', 'company_id', 'department_id']),
        );

        return response()->json(['data' => $summary]);
    }

    public function show(Attendance $attendance): AttendanceResource
    {
        return new AttendanceResource($attendance->load(['employee', 'checkInDevice', 'checkOutDevice']));
    }

    /**
     * Admin manual correction of a scanned row — see
     * AttendanceService::updateByAdmin for the business rules
     * (recompute hours on timestamp change, allow nulling check-out).
     */
    public function update(UpdateAttendanceRequest $request, Attendance $attendance): AttendanceResource
    {
        $updated = $this->attendanceService->updateByAdmin($attendance, $request->validated());

        return new AttendanceResource($updated);
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->attendanceService->deleteByAdmin($attendance);

        return response()->json(null, 204);
    }

    /**
     * Single-click "undo clock-out" — clears check_out_at + derived
     * minutes so the day is reopened. Keeps the admin from having to
     * assemble the right PATCH payload for the common case.
     */
    public function clearCheckOut(Attendance $attendance): AttendanceResource
    {
        return new AttendanceResource($this->attendanceService->clearCheckOutByAdmin($attendance));
    }

    public function monthlySummary(Employee $employee, int $year, int $month): JsonResponse
    {
        // Employees without an assigned work schedule can't have their
        // monthly attendance calculated (no expected hours, no workdays).
        // Return a friendly 422 with a clear reason so the UI can display
        // "assign a schedule first" instead of a generic 500.
        if ($employee->work_schedule_id === null) {
            return response()->json([
                'message' => 'الموظف غير مرتبط بجدول دوام. عيّن له جدولاً من صفحة الموظف ثم أعد المحاولة.',
                'error_code' => 'missing_work_schedule',
            ], 422);
        }

        $summary = $this->calculator->monthlySummary($employee, $year, $month);

        // Wrap in `data` to match every other resource-shaped endpoint in
        // the app, and merge in the minimal employee block the frontend
        // header renders (avatar + name + employee number).
        return response()->json([
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                ],
                ...$summary->toArray(),
            ],
        ]);
    }
}
