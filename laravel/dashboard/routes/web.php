<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DepartmentController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\EmployeeController;
use App\Http\Controllers\Web\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::middleware(['auth'])->group(function () {
    // Dashboard — placeholder đến Sprint 5
    Route::get('/dashboard', fn() => view('dashboard'))->name('dashboard');

    // Nhân viên
    Route::resource('employees', EmployeeController::class);
    Route::get('employees/{employee}/face', [EmployeeController::class, 'showFace'])->name('employees.show-face');
    Route::post('employees/{employee}/face', [EmployeeController::class, 'uploadFace'])->name('employees.upload-face');

    // Phòng ban
    Route::resource('departments', DepartmentController::class);

    // Chấm công
    Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('attendances/{attendance}', [AttendanceController::class, 'show'])->name('attendances.show');
    Route::put('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');

    // Báo cáo
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

    // Thiết bị
    Route::resource('devices', DeviceController::class)->only(['index', 'store', 'destroy']);

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
