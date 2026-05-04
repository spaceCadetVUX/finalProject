<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthDeviceController;
use App\Http\Controllers\Api\EncodingController;
use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\DevicePingController;
use App\Http\Controllers\Api\EmployeeApiController;

/*
|--------------------------------------------------------------------------
| Device API — xác thực bằng static token trong bảng devices
| Header: Authorization: Bearer <device_token>
|--------------------------------------------------------------------------
*/
Route::middleware('device.token')->group(function () {

    // Đăng ký online, cập nhật trạng thái thiết bị
    Route::post('/auth/device', AuthDeviceController::class);

    // Tải xuống face encoding (hỗ trợ delta sync ?updated_since=<unix_ts>)
    Route::get('/encodings', [EncodingController::class, 'index']);

    // Ghi nhận chấm công từ Pi
    Route::post('/attendance', [AttendanceApiController::class, 'store']);
    Route::post('/attendance/batch', [AttendanceApiController::class, 'batch']);

    // Heartbeat — cập nhật last_ping + status=online
    Route::post('/device/ping', [DevicePingController::class, '__invoke']);

    // Thêm nhân viên mới từ Pi4 (Pi tự encode, gửi encoding lên)
    Route::post('/employees', [EmployeeApiController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Admin/Manager API — xác thực bằng Sanctum session (dùng cho SPA/mobile
| nếu mở rộng sau này, hiện tại dashboard dùng Blade server-side)
| Header: Authorization: Bearer <sanctum_token>  hoặc cookie session
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Mở rộng Sprint 6+ nếu cần REST API cho mobile/SPA
});
