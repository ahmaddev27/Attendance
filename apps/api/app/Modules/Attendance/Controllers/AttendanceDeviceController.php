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
        try {
            $device = $this->devices->create($request->validated());

            return (new AttendanceDeviceResource($device))->response()->setStatusCode(201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AttendanceDevice create failed', [
                'payload' => $request->validated(),
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'فشل إنشاء الجهاز: '.$e->getMessage(),
                'debug' => [
                    'exception_class' => get_class($e),
                    'file' => basename($e->getFile()).':'.$e->getLine(),
                ],
            ], 500);
        }
    }

    public function show(AttendanceDevice $device): AttendanceDeviceResource
    {
        return new AttendanceDeviceResource($device);
    }

    public function update(UpdateAttendanceDeviceRequest $request, AttendanceDevice $device)
    {
        try {
            return new AttendanceDeviceResource($this->devices->update($device, $request->validated()));
        } catch (\Throwable $e) {
            // TEMPORARY debug surface: the deploy pipeline swallows the
            // usual laravel.log tail and the client only sees generic
            // 'Server Error'. Return the raw exception message + first
            // trace frame so an admin editing a device can screenshot
            // the actual cause. Remove once the current save flow is
            // confirmed stable.
            \Illuminate\Support\Facades\Log::error('AttendanceDevice update failed', [
                'device_id' => $device->id,
                'payload' => $request->validated(),
                'exception' => $e->getMessage(),
                'trace_first' => $e->getTraceAsString() ? explode("\n", $e->getTraceAsString())[0] : null,
            ]);

            return response()->json([
                'message' => 'فشل تحديث الجهاز: '.$e->getMessage(),
                'debug' => [
                    'exception_class' => get_class($e),
                    'file' => basename($e->getFile()).':'.$e->getLine(),
                ],
            ], 500);
        }
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
