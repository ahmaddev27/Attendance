<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController;
use App\Livewire\Admin\Attendance\AttendanceList;
use App\Livewire\Admin\Employees\EmployeeList;
use App\Livewire\Admin\Leaves\LeaveList;
use App\Livewire\Admin\Overview;
use App\Livewire\Admin\Settings\SettingsForm;
use App\Livewire\Admin\SmsLogs\SmsLogList;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('scan.index'));

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::get('/scan/attendance', [ScanController::class, 'attendanceForm'])->name('scan.attendance.form');
    Route::post('/scan/attendance/preview', [ScanController::class, 'attendancePreview'])->name('scan.attendance.preview');
    Route::post('/scan/attendance/confirm', [ScanController::class, 'attendanceConfirm'])->name('scan.attendance.confirm');

    Route::post('/scan/leave', [ScanController::class, 'leaveSubmit'])->name('scan.leave.submit');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Overview::class)->name('overview');

    Route::get('/employees', EmployeeList::class)->name('employees.index');

    Route::get('/leaves', LeaveList::class)->name('leaves.index');

    Route::get('/attendance', AttendanceList::class)->name('attendance.index');

    Route::get('/sms-logs', SmsLogList::class)->name('sms-logs.index');

    Route::get('/settings', SettingsForm::class)->name('settings.index');
});
