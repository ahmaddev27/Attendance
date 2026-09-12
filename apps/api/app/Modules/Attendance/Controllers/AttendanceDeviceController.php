<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Modules\Attendance\Requests\StoreAttendanceDeviceRequest;
use App\Modules\Attendance\Requests\UpdateAttendanceDeviceRequest;
use App\Modules\Attendance\Resources\AttendanceDeviceResource;
use App\Modules\Attendance\Services\AttendanceDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceDeviceController extends Controller
{
    public function __construct(
        private readonly AttendanceDeviceService $devices,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return AttendanceDeviceResource::collection($this->devices->paginate());
    }

    public function store(StoreAttendanceDeviceRequest $request): JsonResponse
    {
        $device = $this->devices->create($request->validated());

        return (new AttendanceDeviceResource($device))->response()->setStatusCode(201);
    }

    public function show(AttendanceDevice $device): AttendanceDeviceResource
    {
        return new AttendanceDeviceResource($device);
    }

    public function update(UpdateAttendanceDeviceRequest $request, AttendanceDevice $device): AttendanceDeviceResource
    {
        return new AttendanceDeviceResource($this->devices->update($device, $request->validated()));
    }

    public function destroy(AttendanceDevice $device): JsonResponse
    {
        $this->devices->delete($device);

        return response()->json(null, 204);
    }

    public function rotateToken(AttendanceDevice $device): AttendanceDeviceResource
    {
        return new AttendanceDeviceResource($this->devices->rotateToken($device));
    }
}
