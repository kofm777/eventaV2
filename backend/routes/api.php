<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check
Route::get('/health', [HealthController::class, 'health']);

// API v1 routes
Route::prefix('v1')->group(function () {
    
    // Public routes
    Route::post('/register', [RegistrationController::class, 'register'])
        ->middleware('throttle:5,1'); // 5 requests per minute
    
    Route::post('/scan', [ScanController::class, 'scan'])
        ->middleware('throttle:30,1'); // 30 scans per minute
    
    // Admin authentication
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');
        
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });
    
    // Admin protected routes
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        // Participants management
        Route::get('/participants', [AdminController::class, 'getParticipants']);
        Route::post('/participants/{id}/accept', [AdminController::class, 'acceptParticipant']);
        Route::post('/participants/{id}/reject', [AdminController::class, 'rejectParticipant']);
        
        // Scans management
        Route::get('/scans', [ScanController::class, 'getRecentScans']);
    });
});
