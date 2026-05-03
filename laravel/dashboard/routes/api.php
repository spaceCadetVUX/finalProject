<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthDeviceController;
use App\Http\Controllers\Api\EncodingController;
use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\DevicePingController;

/*
|--------------------------------------------------------------------------
| Pi4 Device API Routes
|--------------------------------------------------------------------------
|
| All routes here are protected by DeviceTokenMiddleware which validates
| the Bearer token in the Authorization header against the devices table.
|
*/

// Auth — no middleware, token IS the credential
Route::post('/auth/device', AuthDeviceController::class);

// Protected routes — require valid device token
Route::middleware('device.token')->group(function () {
    Route::get('/encodings', [EncodingController::class, 'index']);

    Route::post('/attendance', [AttendanceApiController::class, 'store']);
    Route::post('/attendance/batch', [AttendanceApiController::class, 'batch']);

    Route::post('/device/ping', [DevicePingController::class, '__invoke']);
});
