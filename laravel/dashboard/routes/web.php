<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AttendanceController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DepartmentController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\EmployeeController;
use App\Http\Controllers\Web\MyAttendanceController;
use App\Http\Controllers\Web\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::middleware(['auth'])->group(function () {

    // ── Tất cả roles ────────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/dashboard/recent', [DashboardController::class, 'recentCheckins'])->name('dashboard.recent');
    Route::get('/my-attendance', [MyAttendanceController::class, 'index'])->name('my-attendance');

    // Chấm công — data tự scope trong controller theo role
    Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::get('attendances/{attendance}', [AttendanceController::class, 'show'])->name('attendances.show');

    // ── Admin + Manager ──────────────────────────────────────────────────────
    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::put('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // ── Admin only ───────────────────────────────────────────────────────────
    Route::middleware('role:super_admin,admin')->group(function () {
        Route::resource('employees', EmployeeController::class);
        Route::get('employees/{employee}/face', [EmployeeController::class, 'showFace'])->name('employees.show-face');
        Route::post('employees/{employee}/face', [EmployeeController::class, 'uploadFace'])->name('employees.upload-face');

        Route::resource('departments', DepartmentController::class);

        Route::resource('devices', DeviceController::class)->only(['index', 'store', 'destroy']);
    });

    // ── Profile (Breeze) ─────────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
