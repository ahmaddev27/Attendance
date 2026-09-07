<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Modules\Attendance\Repositories\AttendanceRepository;
use App\Modules\Attendance\Resources\AttendanceResource;
use App\Modules\Attendance\Services\WorkingHoursCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceRepository $attendances,
        private readonly WorkingHoursCalculator $calculator,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $attendances = $this->attendances->paginate(
            $request->only(['employee_id', 'status', 'date_from', 'date_to']),
            (int) $request->integer('per_page', 15),
        );

        return AttendanceResource::collection($attendances);
    }

    public function show(Attendance $attendance): AttendanceResource
    {
        return new AttendanceResource($attendance->load(['employee', 'checkInDevice', 'checkOutDevice']));
    }

    public function monthlySummary(Employee $employee, int $year, int $month): JsonResponse
    {
        $summary = $this->calculator->monthlySummary($employee, $year, $month);

        return response()->json($summary->toArray());
    }
}
