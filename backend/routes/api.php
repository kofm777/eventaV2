<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'health']);

Route::prefix('v1')->group(function () {
    // Public
    Route::post('/register', [RegistrationController::class, 'register'])
        ->middleware('throttle:5,1');

    // 🔐 Protected scan endpoints
    Route::post('/scan-fair', [ScanController::class, 'scanFair'])
        ->middleware('throttle:30,1');

    Route::post('/scan-conference', [ScanController::class, 'scanConference'])
        ->middleware('throttle:30,1');

    // Admin auth
    Route::prefix('auth')->group(function () {
        Route::post('/admin/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // Admin routes
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::get('/participants', [AdminController::class, 'getParticipants']);
        Route::post('/participants/{id}/accept', [AdminController::class, 'acceptParticipant']);
        Route::post('/participants/{id}/reject', [AdminController::class, 'rejectParticipant']);
        Route::delete('/participants/{id}', [AdminController::class, 'deleteParticipant']);
        Route::get('/participants/{id}/badge', [AdminController::class, 'downloadBadge']);
        Route::get('/scans', [ScanController::class, 'getRecentScans']);
    });
});