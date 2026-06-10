<?php

use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TicketController;
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

    // Public events (PUBLISHED only)
    Route::get('/events', [PublicEventController::class, 'index'])
        ->middleware('throttle:30,1');
    Route::get('/events/{slug}', [PublicEventController::class, 'show'])
        ->middleware('throttle:30,1');

    // Guest ticket purchase lifecycle
    Route::post('/events/{slug}/purchase', [PurchaseController::class, 'purchase'])
        ->middleware('throttle:5,1');
    Route::post('/orders/{order_number}/confirm', [PurchaseController::class, 'confirm'])
        ->middleware('throttle:10,1');
    Route::get('/orders/{order_number}', [PurchaseController::class, 'show'])
        ->middleware('throttle:30,1');

    // Payment gateway webhook (STUB) — API route, no CSRF.
    Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle'])
        ->middleware('throttle:60,1');

    // Public no-login ticket access by secure token
    Route::get('/tickets/{token}', [TicketController::class, 'show'])
        ->middleware('throttle:30,1');
    Route::get('/tickets/{token}/badge', [TicketController::class, 'badge'])
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
        Route::get('/dashboard', [AdminController::class, 'getDashboardStats']);
        Route::post('/participants/{id}/accept', [AdminController::class, 'acceptParticipant']);
        Route::post('/participants/{id}/reject', [AdminController::class, 'rejectParticipant']);
        Route::delete('/participants/{id}', [AdminController::class, 'deleteParticipant']);
        Route::get('/participants/{id}/badge', [AdminController::class, 'downloadBadge']);
        Route::get('/scans', [ScanController::class, 'getRecentScans']);

        // Events CRUD (admin)
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::get('/events/{id}', [EventController::class, 'show']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
    });
});