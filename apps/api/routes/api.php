<?php

use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\AttendanceDeviceController;
use App\Modules\Attendance\Controllers\HolidayController;
use App\Modules\Attendance\Controllers\ScanController;
use App\Modules\Attendance\Controllers\WorkScheduleController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Employees\Controllers\EmployeeController;
use App\Modules\Organization\Controllers\DepartmentController;
use App\Modules\Organization\Controllers\PositionController;
use App\Modules\Organization\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toIso8601String(),
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// M2 — Employees + Organization Structure. Permission-based authorization
// (per role) is layered on top of auth:sanctum in a later milestone.
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('org')->group(function () {
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('teams', TeamController::class);
        Route::apiResource('positions', PositionController::class);
    });

    Route::apiResource('employees', EmployeeController::class);
    Route::post('/employees/{employee}/restore', [EmployeeController::class, 'restore']);
});

// M3 — Attendance + Working Hours Engine.
Route::prefix('scan')->group(function () {
    Route::post('/check-in', [ScanController::class, 'checkIn'])->middleware('throttle:30,1');
    Route::post('/check-out', [ScanController::class, 'checkOut'])->middleware('throttle:30,1');

    // Kiosk display polling: no employee credential involved, so it gets a
    // more generous limit than the scan actions above.
    Route::get('/device/{qrToken}', [ScanController::class, 'deviceInfo'])->middleware('throttle:120,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('attendance', AttendanceController::class)->only(['index', 'show']);
    Route::get('/attendance/employee/{employee}/monthly/{year}/{month}', [AttendanceController::class, 'monthlySummary']);

    Route::apiResource('attendance-devices', AttendanceDeviceController::class)
        ->parameters(['attendance-devices' => 'device']);
    Route::post('/attendance-devices/{device}/rotate', [AttendanceDeviceController::class, 'rotateToken']);

    Route::apiResource('holidays', HolidayController::class);

    Route::apiResource('work-schedules', WorkScheduleController::class)
        ->parameters(['work-schedules' => 'schedule']);
});
