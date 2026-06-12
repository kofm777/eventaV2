<?php

use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrganizerController as AdminOrganizerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrganizerSignupController;
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

    // Public organizer self-signup (hybrid: creates a PENDING organizer + owner admin,
    // NO token issued — awaits super-admin approval). No auth, no organizer context.
    Route::post('/organizers/signup', [OrganizerSignupController::class, 'signup'])
        ->middleware('throttle:5,1');

    // Public events (PUBLISHED only)
    Route::get('/events', [PublicEventController::class, 'index'])
        ->middleware('throttle:30,1');
    Route::get('/events/{slug}', [PublicEventController::class, 'show'])
        ->middleware('throttle:30,1');

    // Guest ticket purchase lifecycle
    // DISABLED until real payment gateway (Phase 3) — see CORRECTION_PLAN.md
    // Route::post('/events/{slug}/purchase', [PurchaseController::class, 'purchase'])
    //     ->middleware('throttle:5,1');
    // Route::post('/orders/{order_number}/confirm', [PurchaseController::class, 'confirm'])
    //     ->middleware('throttle:10,1');
    Route::get('/orders/{order_number}', [PurchaseController::class, 'show'])
        ->middleware('throttle:30,1');

    // Payment gateway webhook (STUB) — API route, no CSRF.
    // DISABLED until real payment gateway (Phase 3) — see CORRECTION_PLAN.md
    // Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle'])
    //     ->middleware('throttle:60,1');

    // Public no-login ticket access by secure token
    Route::get('/tickets/{token}', [TicketController::class, 'show'])
        ->middleware('throttle:30,1');
    Route::get('/tickets/{token}/badge', [TicketController::class, 'badge'])
        ->middleware('throttle:30,1');

    // Scan endpoints: auth + active organizer + role. STAFF is allowed HERE and only
    // here (scan-only). Super-admin/owner/admin may also scan. Auto-scoped by Phase 0.
    Route::middleware([
        'auth:sanctum',
        'organizer.active',
        'role:' . implode(',', [
            \App\Models\Admin::ROLE_SUPERADMIN,
            \App\Models\Admin::ROLE_OWNER,
            \App\Models\Admin::ROLE_ADMIN,
            \App\Models\Admin::ROLE_STAFF,
        ]),
    ])->group(function () {
        Route::post('/scan-fair', [ScanController::class, 'scanFair'])
            ->middleware('throttle:30,1');

        Route::post('/scan-conference', [ScanController::class, 'scanConference'])
            ->middleware('throttle:30,1');
    });

    // Admin auth
    Route::prefix('auth')->group(function () {
        Route::post('/admin/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // Role CSVs (built from the Admin constants so the matrix stays in sync with the model).
    $management = 'role:' . implode(',', [
        \App\Models\Admin::ROLE_SUPERADMIN,
        \App\Models\Admin::ROLE_OWNER,
        \App\Models\Admin::ROLE_ADMIN,
    ]);
    $superadminOnly = 'role:' . \App\Models\Admin::ROLE_SUPERADMIN;

    // SUPER-ADMIN platform organizers console. role:superadmin ONLY; NOT gated by
    // organizer.active (super-admin has no organizer to check).
    Route::middleware(['auth:sanctum', $superadminOnly])
        ->prefix('admin')->group(function () {
            Route::get('/organizers', [AdminOrganizerController::class, 'index']);
            Route::post('/organizers/{id}/approve', [AdminOrganizerController::class, 'approve']);
            Route::post('/organizers/{id}/suspend', [AdminOrganizerController::class, 'suspend']);
            Route::post('/organizers/{id}/reactivate', [AdminOrganizerController::class, 'reactivate']);
        });

    // Organizer console (owner/admin manage only their OWN rows via the Phase 0 scope;
    // super-admin sees all). Gated by auth + active organizer + management roles.
    Route::middleware(['auth:sanctum', 'organizer.active', $management])
        ->prefix('admin')->group(function () {
            Route::get('/participants', [AdminController::class, 'getParticipants']);
            Route::get('/dashboard', [AdminController::class, 'getDashboardStats']);
            Route::post('/participants/{id}/accept', [AdminController::class, 'acceptParticipant']);
            Route::post('/participants/{id}/reject', [AdminController::class, 'rejectParticipant']);
            Route::delete('/participants/{id}', [AdminController::class, 'deleteParticipant']);
            Route::get('/participants/{id}/badge', [AdminController::class, 'downloadBadge']);
            Route::get('/scans', [ScanController::class, 'getRecentScans']);

            // Orders (NEW): Order already BelongsToOrganizer -> auto-scoped.
            Route::get('/orders', [OrderController::class, 'index']);

            // Events CRUD (admin)
            Route::get('/events', [EventController::class, 'index']);
            Route::post('/events', [EventController::class, 'store']);
            Route::get('/events/{id}', [EventController::class, 'show']);
            Route::put('/events/{id}', [EventController::class, 'update']);
            Route::delete('/events/{id}', [EventController::class, 'destroy']);
        });
});