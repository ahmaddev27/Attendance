<?php

use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\AttendanceDeviceController;
use App\Modules\Attendance\Controllers\HolidayController;
use App\Modules\Attendance\Controllers\ScanController;
use App\Modules\Attendance\Controllers\WorkScheduleController;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Employees\Controllers\EmployeeController;
use App\Modules\Leaves\Controllers\EmployeeLeavesController;
use App\Modules\Leaves\Controllers\LeaveBalanceController;
use App\Modules\Leaves\Controllers\LeaveRequestController;
use App\Modules\Leaves\Controllers\LeaveTypeController;
use App\Modules\Organization\Controllers\DepartmentController;
use App\Modules\Organization\Controllers\PositionController;
use App\Modules\Organization\Controllers\TeamController;
use App\Modules\AI\Controllers\MotivationController;
use App\Modules\Analytics\Controllers\AnalyticsController;
use App\Modules\Notifications\Controllers\MyNotificationsController;
use App\Modules\Push\Controllers\PushTokenController;
use App\Modules\Reports\Controllers\AdminDashboardController;
use App\Modules\Reports\Controllers\AttendanceReportController;
use App\Modules\Reports\Controllers\AuditLogController;
use App\Modules\Reports\Controllers\EmployeeDashboardController;
use App\Modules\Search\Controllers\SearchController;
use App\Modules\Settings\Controllers\SettingsController;
use App\Modules\Requests\Controllers\ApprovalInboxController;
use App\Modules\Requests\Controllers\MyRequestsController;
use App\Modules\Requests\Controllers\RequestController;
use App\Modules\Tasks\Controllers\MyTasksController;
use App\Modules\Tasks\Controllers\TaskAttachmentController;
use App\Modules\Tasks\Controllers\TaskCommentController;
use App\Modules\Tasks\Controllers\TaskController;
use App\Modules\Tasks\Controllers\TaskPriorityController;
use App\Modules\Tasks\Controllers\TaskStatusController;
use App\Modules\Tasks\Controllers\TaskTagController;
use App\Modules\Workflow\Controllers\RequestTypeController;
use App\Modules\Workflow\Controllers\WorkflowController;
use App\Modules\Workflow\Controllers\WorkflowStepController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toIso8601String(),
    ]);
});

// Reverb / Echo channel-auth endpoint. api.php already prefixes '/api',
// so we DON'T re-add it here — otherwise the route ends up at
// /api/api/broadcasting/auth and Echo's default authEndpoint 404s.
Broadcast::routes(['middleware' => ['auth:sanctum']]);

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
    // Admin-only: hand the employee a new password + create a User for
    // them if one doesn't already exist. Returns the plaintext once.
    Route::post('/employees/{employee}/reset-password', [EmployeeController::class, 'resetPassword'])
        ->middleware('permission:manage-users');
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

// M4 — Leaves: types + balances + requests.
Route::middleware('auth:sanctum')->group(function () {
    // Admin
    Route::apiResource('leave-types', LeaveTypeController::class);
    Route::apiResource('leave-requests', LeaveRequestController::class)->except(['update']);
    Route::post('/leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject']);
    Route::post('/leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel']);
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index']); // ?employee_id=X&year=Y
    Route::post('/leave-balances/adjust', [LeaveBalanceController::class, 'adjust']);

    // Employee self-service (uses request()->user()->employee)
    Route::prefix('me/leaves')->group(function () {
        Route::get('/', [EmployeeLeavesController::class, 'index']); // my leave requests
        Route::get('/balances', [EmployeeLeavesController::class, 'balances']); // my balances
        Route::post('/', [EmployeeLeavesController::class, 'store']); // submit
        Route::post('/{leave_request}/cancel', [EmployeeLeavesController::class, 'cancel']);
    });
});

// M6 — Tasks: statuses/priorities/tags (admin lookups) + tasks (with
// subtasks) + comments + file attachments. Phase 1 deliberately has no
// projects/sprints — those tables land in Phase 2 (see
// docs/v2/03-phase-1-plan.md's M6 section).
Route::middleware('auth:sanctum')->group(function () {
    // Config (admin)
    Route::apiResource('task-statuses', TaskStatusController::class);
    Route::apiResource('task-priorities', TaskPriorityController::class);
    Route::apiResource('task-tags', TaskTagController::class);

    // Tasks. /tasks/kanban is a static path and must be registered before
    // apiResource's GET /tasks/{task} below it — otherwise Laravel would
    // match "kanban" as the {task} route-model-binding value instead of
    // reaching TaskController::kanban().
    Route::get('/tasks/kanban', [TaskController::class, 'kanban']);
    Route::apiResource('tasks', TaskController::class);
    Route::post('/tasks/{task}/restore', [TaskController::class, 'restore']);
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete']);

    // Task comments
    Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index']);
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store']);
    Route::put('/comments/{comment}', [TaskCommentController::class, 'update']);
    Route::delete('/comments/{comment}', [TaskCommentController::class, 'destroy']);

    // Attachments. The download link is additionally signed
    // (TaskAttachmentService::signedDownloadUrl()) — the signature is
    // what actually authorizes the download, auth:sanctum on top of it
    // just keeps it consistent with every other route in this file.
    Route::get('/tasks/{task}/attachments', [TaskAttachmentController::class, 'index']);
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'upload']);
    Route::get('/attachments/{media}/download', [TaskAttachmentController::class, 'download'])
        ->name('tasks.attachments.download')
        ->middleware('signed');
    Route::delete('/attachments/{media}', [TaskAttachmentController::class, 'destroy']);

    // My tasks (uses request()->user()->employee)
    Route::prefix('me/tasks')->group(function () {
        Route::get('/', [MyTasksController::class, 'index']); // assigned to me
        Route::get('/created', [MyTasksController::class, 'created']); // created by me
    });
});

// M5 — Generic Workflow Engine + Request Builder.
Route::middleware('auth:sanctum')->group(function () {
    // Admin: workflow management
    Route::apiResource('workflows', WorkflowController::class);
    Route::prefix('workflows/{workflow}')->group(function () {
        Route::apiResource('steps', WorkflowStepController::class);
        Route::post('/steps/reorder', [WorkflowStepController::class, 'reorder']);
    });
    Route::apiResource('request-types', RequestTypeController::class);

    // Admin: requests inbox (all requests)
    Route::apiResource('requests', RequestController::class)->only(['index', 'show']);
    Route::post('/requests/{request}/approve', [RequestController::class, 'approve']);
    Route::post('/requests/{request}/reject', [RequestController::class, 'reject']);
    Route::post('/requests/{request}/return', [RequestController::class, 'return']);
    Route::post('/requests/{request}/forward', [RequestController::class, 'forward']);

    // Approver's inbox
    Route::get('/approvals/inbox', [ApprovalInboxController::class, 'index']);

    // Employee self (uses request()->user()->employee)
    Route::prefix('me/requests')->group(function () {
        Route::get('/', [MyRequestsController::class, 'index']);
        Route::get('/{request}', [MyRequestsController::class, 'show']);
        Route::post('/', [MyRequestsController::class, 'store']); // submit
        Route::post('/{request}/cancel', [MyRequestsController::class, 'cancel']);
    });

    // Admin-only endpoints — protected by spatie/laravel-permission.
    // super-admin has every permission via RolePermissionSeeder;
    // department-manager and above should be granted per-permission when
    // finer role modelling lands.
    Route::prefix('admin')->group(function () {
        // Dashboard summary — safe aggregate counts, but still admin-only.
        Route::middleware('permission:view-reports')
            ->get('/dashboard/kpis', [AdminDashboardController::class, 'kpis']);

        // Reports (attendance monthly, ...)
        Route::middleware('permission:view-reports')->group(function () {
            Route::get('/reports/attendance/monthly', [AttendanceReportController::class, 'monthly']);
        });

        // Analytics dashboards (Phase 3). Same permission gate as reports;
        // each endpoint returns the same {data, meta} envelope so the
        // frontend can share the fetch layer.
        Route::middleware('permission:view-reports')
            ->prefix('analytics')
            ->group(function () {
                Route::get('/attendance/kpis',    [AnalyticsController::class, 'attendanceKpis']);
                Route::get('/attendance/heatmap', [AnalyticsController::class, 'attendanceHeatmap']);
                Route::get('/leaves/patterns',    [AnalyticsController::class, 'leavePatterns']);
                Route::get('/tasks/performance',  [AnalyticsController::class, 'taskPerformance']);
                Route::get('/employees/summary',  [AnalyticsController::class, 'employeeSummary']);
            });

        // Audit log viewer
        Route::middleware('permission:view-audit-logs')
            ->get('/audit-log', [AuditLogController::class, 'index']);

        // System settings (mail + sms credentials, editable at runtime).
        // Only super-admins should touch these — permission gate + spatie
        // role check (super-admin implicitly gets every permission via
        // RolePermissionSeeder).
        Route::middleware('permission:manage-users')->group(function () {
            Route::get('/settings', [SettingsController::class, 'index']);
            Route::put('/settings', [SettingsController::class, 'update']);
        });
    });

    // Employee's personal dashboard (M7). Scoped to $request->user()
    // at the service layer — no permission gate needed.
    Route::get('/me/dashboard/kpis', [EmployeeDashboardController::class, 'kpis']);

    // Notifications (M7). Every user sees only their own inbox — the
    // controller uses $request->user()->notifications, not a global list.
    Route::prefix('me/notifications')->group(function () {
        Route::get('/', [MyNotificationsController::class, 'index']);
        Route::get('/unread-count', [MyNotificationsController::class, 'unreadCount']);
        Route::post('/read-all', [MyNotificationsController::class, 'markAllRead']);
        Route::post('/{id}/read', [MyNotificationsController::class, 'markRead']);
    });

    // Mobile push tokens. The RN app registers on login/launch and
    // revokes on logout — one row per (user, device_id) so a fresh
    // Expo token overwrites in place instead of piling up.
    Route::prefix('me/push-tokens')->group(function () {
        Route::post('/', [PushTokenController::class, 'register']);
        Route::delete('/', [PushTokenController::class, 'revokeAll']);
        Route::delete('/{deviceId}', [PushTokenController::class, 'revokeDevice']);
    });

    // AI Motivation (M8) — one Claude-backed line per user per day,
    // cached in redis and served with a canned fallback if the API
    // path fails, so the dashboard never 500s over an LLM outage.
    Route::get('/me/motivation', [MotivationController::class, 'show']);

    // Global cross-index search. Every authenticated user may hit this;
    // per-row visibility filtering is out of scope for M9 and lands in a
    // follow-up milestone.
    Route::get('/search', [SearchController::class, 'query']);
});
