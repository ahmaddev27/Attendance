<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController;
use App\Livewire\Admin\Employees\EmployeeForm;
use App\Livewire\Admin\Employees\EmployeeList;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::get('/scan/attendance', [ScanController::class, 'attendanceForm'])->name('scan.attendance.form');
    Route::post('/scan/attendance/preview', [ScanController::class, 'attendancePreview'])->name('scan.attendance.preview');
    Route::post('/scan/attendance/confirm', [ScanController::class, 'attendanceConfirm'])->name('scan.attendance.confirm');

    // TODO(Task 4.2): replace with the real leave-request form.
    Route::get('/scan/leave', fn () => 'coming soon')->name('scan.leave.form');
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
});
