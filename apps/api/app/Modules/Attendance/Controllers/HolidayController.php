<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Modules\Attendance\Requests\StoreHolidayRequest;
use App\Modules\Attendance\Requests\UpdateHolidayRequest;
use App\Modules\Attendance\Resources\HolidayResource;
use App\Modules\Attendance\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Referenced by the routes in the module spec but not itemized in its
 * Controllers/ listing (which only named AttendanceController,
 * ScanController and AttendanceDeviceController) — added because
 * `Route::apiResource('holidays', HolidayController::class)` needs it.
 */
class HolidayController extends Controller
{
    public function __construct(
        private readonly HolidayService $holidays,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return HolidayResource::collection($this->holidays->paginate());
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = $this->holidays->create($request->validated());

        return (new HolidayResource($holiday))->response()->setStatusCode(201);
    }

    public function show(Holiday $holiday): HolidayResource
    {
        return new HolidayResource($holiday);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): HolidayResource
    {
        return new HolidayResource($this->holidays->update($holiday, $request->validated()));
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        $this->holidays->delete($holiday);

        return response()->json(null, 204);
    }
}
