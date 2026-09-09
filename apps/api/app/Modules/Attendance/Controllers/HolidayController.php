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
use Illuminate\Http\Request;
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

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['year', 'type']);

        // Default path: return every matching holiday as a flat list —
        // the admin holidays screen reads `data.data` as an array and
        // filters client-side. Opt in to pagination with `per_page`.
        if ($request->filled('per_page')) {
            $perPage = max(1, (int) $request->query('per_page'));

            return HolidayResource::collection($this->holidays->paginate($filters, $perPage));
        }

        return HolidayResource::collection($this->holidays->list($filters));
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
