<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SensorRecordsController;
use App\Http\Controllers\Api\FieldReportController;
use App\Http\Controllers\Api\StationManagementController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public authentication routes
Route::post('/v1/register', [AuthController::class, 'register']);
Route::post('/v1/login', [AuthController::class, 'login']);

// Protected Routes (Requires Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v1/dashboard', [DashboardController::class, 'index']);
    Route::post('/v1/logout', [AuthController::class, 'logout']);
    Route::post('/v1/account/password', [AuthController::class, 'changePassword']);
    Route::get('/v1/records/export', [SensorRecordsController::class, 'export']);
    Route::get('/v1/records', [SensorRecordsController::class, 'index']);
    Route::get('/v1/reports', [FieldReportController::class, 'index']);
    Route::post('/v1/reports', [FieldReportController::class, 'store']);

    // Role-protected routes for your capstone defense requirements
    Route::middleware('role:admin')->group(function () {
        Route::get('/v1/admin/stations', [StationManagementController::class, 'index']);
        Route::post('/v1/admin/stations', [StationManagementController::class, 'store']);
        Route::patch('/v1/admin/stations/{station}', [StationManagementController::class, 'update']);
        Route::patch('/v1/admin/stations/{station}/threshold', [StationManagementController::class, 'updateThreshold']);
        Route::delete('/v1/admin/stations/{station}', [StationManagementController::class, 'destroy']);
        Route::patch('/v1/admin/reports/{fieldReport}/status', [FieldReportController::class, 'updateStatus']);
    });
});
