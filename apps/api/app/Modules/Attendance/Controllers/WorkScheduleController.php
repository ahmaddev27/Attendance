<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use App\Modules\Attendance\Requests\StoreWorkScheduleRequest;
use App\Modules\Attendance\Requests\UpdateWorkScheduleRequest;
use App\Modules\Attendance\Resources\WorkScheduleResource;
use App\Modules\Attendance\Services\WorkScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Referenced by the routes in the module spec but not itemized in its
 * Controllers/ listing (which only named AttendanceController,
 * ScanController and AttendanceDeviceController) — added because
 * `Route::apiResource('work-schedules', WorkScheduleController::class)`
 * needs it.
 */
class WorkScheduleController extends Controller
{
    public function __construct(
        private readonly WorkScheduleService $schedules,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return WorkScheduleResource::collection($this->schedules->paginate());
    }

    public function store(StoreWorkScheduleRequest $request): JsonResponse
    {
        $schedule = $this->schedules->create($request->validated());

        return (new WorkScheduleResource($schedule))->response()->setStatusCode(201);
    }

    public function show(WorkSchedule $schedule): WorkScheduleResource
    {
        return new WorkScheduleResource($schedule);
    }

    public function update(UpdateWorkScheduleRequest $request, WorkSchedule $schedule): WorkScheduleResource
    {
        return new WorkScheduleResource($this->schedules->update($schedule, $request->validated()));
    }

    public function destroy(WorkSchedule $schedule): JsonResponse
    {
        $this->schedules->delete($schedule);

        return response()->json(null, 204);
    }
}
