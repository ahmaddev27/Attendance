<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController;
use App\Livewire\Admin\Attendance\AttendanceList;
use App\Livewire\Admin\Employees\EmployeeForm;
use App\Livewire\Admin\Employees\EmployeeList;
use App\Livewire\Admin\Leaves\LeaveList;
use App\Livewire\Admin\Settings\SettingsForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::get('/scan/attendance', [ScanController::class, 'attendanceForm'])->name('scan.attendance.form');
    Route::post('/scan/attendance/preview', [ScanController::class, 'attendancePreview'])->name('scan.attendance.preview');
    Route::post('/scan/attendance/confirm', [ScanController::class, 'attendanceConfirm'])->name('scan.attendance.confirm');

    Route::get('/scan/leave', [ScanController::class, 'leaveForm'])->name('scan.leave.form');
    Route::post('/scan/leave', [ScanController::class, 'leaveSubmit'])->name('scan.leave.submit');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.overview')->name('overview');

    Route::get('/employees', EmployeeList::class)->name('employees.index');
    Route::get('/employees/create', EmployeeForm::class)->name('employees.create');
    Route::get('/employees/{employee}/edit', EmployeeForm::class)->name('employees.edit');

    Route::get('/leaves', LeaveList::class)->name('leaves.index');

    Route::get('/attendance', AttendanceList::class)->name('attendance.index');

    Route::get('/settings', SettingsForm::class)->name('settings.index');
});
