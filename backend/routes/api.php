<?php

use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrganizerController as AdminOrganizerController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\TicketTypeController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendeeAuthController;
use App\Http\Controllers\AttendeeOrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrganizerSignupController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PublicDiscoverController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicOrganizerController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketRetrievalController;
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

    // PHASE 4 — public marketplace. Cross-organizer, curated surface: published +
    // visibility=marketplace + organizer.status=active, gated EXPLICITLY in the query
    // (the tenant scope is a no-op for public). Leaves /events unchanged.
    Route::get('/discover', [PublicDiscoverController::class, 'index'])
        ->middleware('throttle:30,1');

    // PHASE 4 — public per-organizer storefront. Active-org-only (404 + identical
    // message otherwise => non-enumerable); returns the org's branding + published events.
    Route::get('/organizers/{slug}', [PublicOrganizerController::class, 'show'])
        ->middleware('throttle:30,1');

    // PHASE 4 — attendee find-my-tickets (audit H9). Email-keyed magic-link re-send.
    // ALWAYS returns a generic ok:true (no email-existence leak); tight 3/10min throttle
    // KEYED BY EMAIL (named 'find-tickets' limiter in RouteServiceProvider) blocks probing
    // a single address even across IPs. POST so it never collides with GET /tickets/{token}.
    Route::post('/tickets/find', [TicketRetrievalController::class, 'send'])
        ->middleware('throttle:find-tickets');

    // Guest ticket purchase lifecycle (Phase 3 — RE-ENABLED with the secure flow).
    //   purchase: creates a PENDING_PAYMENT order (+ order_items); free orders
    //             (amount_total 0) issue immediately, paid orders return a
    //             client_action (stub: auto_confirm, flouci: redirect URL).
    //   confirm : driver-confirm. Stub auto-confirms (demo); Flouci re-verifies
    //             SERVER-SIDE via verify_payment and issues ONLY on result SUCCESS.
    //             This is also the Flouci return/verify endpoint — the buyer's
    //             /payment/flouci/return page POSTs {payment_id} here; a forged
    //             return cannot mint tickets because verification is server-to-server.
    Route::post('/events/{slug}/purchase', [PurchaseController::class, 'purchase'])
        ->middleware('throttle:5,1');
    Route::post('/orders/{order_number}/confirm', [PurchaseController::class, 'confirm'])
        ->middleware('throttle:10,1');
    Route::get('/orders/{order_number}', [PurchaseController::class, 'show'])
        ->middleware('throttle:30,1');

    // Payment gateway webhook — API route, no CSRF. RE-ENABLED for future signed-webhook
    // gateways; INERT for Flouci (handleWebhook returns null => 404), which uses pull-based
    // verification on return instead. The only thing that flips an order to PAID in prod
    // is a successful server-side verify_payment.
    Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle'])
        ->middleware('throttle:60,1');

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

    // PHASE 6 — attendee (public account) auth + wallet. A THIRD identity on its OWN
    // 'sanctum-attendee' guard (provider 'attendees'); fully separate from the admin
    // routes below. register/login are public (throttle:5,1, like admin login); the
    // rest sit behind auth:sanctum-attendee + the EnsureAttendee backstop so an admin
    // token is rejected at BOTH the guard and the middleware.
    Route::prefix('attendee')->group(function () {
        Route::post('/register', [AttendeeAuthController::class, 'register'])
            ->middleware('throttle:5,1');
        Route::post('/login', [AttendeeAuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware(['auth:sanctum-attendee', 'attendee'])->group(function () {
            Route::post('/logout', [AttendeeAuthController::class, 'logout']);
            Route::get('/me', [AttendeeAuthController::class, 'me']);
            Route::get('/my-tickets', [AttendeeOrderController::class, 'myTickets']);
        });
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

            // PHASE 5 — super-admin platform control plane (analytics + payout ledger
            // + per-organizer commission). Reads the Phase 3 capture columns; relies on
            // the super-admin scope bypass so Order::query() aggregates every organizer.
            Route::get('/platform/analytics', [PlatformController::class, 'analytics']);
            Route::get('/platform/balances', [PlatformController::class, 'balances']);
            Route::put('/organizers/{id}/commission-rate', [AdminOrganizerController::class, 'setCommissionRate']);
            Route::get('/organizers/{id}/payouts', [PlatformController::class, 'payoutHistory']);
            Route::post('/organizers/{id}/payouts', [PlatformController::class, 'recordPayout']);
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

            // Ticket types (Phase 2): nested under an event, auto-scoped by the Phase 0
            // global scope. Purchase/confirm/webhook are RE-ENABLED in Phase 3 (public
            // routes above) with the secure driver flow; scan routes unchanged.
            Route::get('/events/{event}/ticket-types', [TicketTypeController::class, 'index']);
            Route::post('/events/{event}/ticket-types', [TicketTypeController::class, 'store']);
            Route::put('/events/{event}/ticket-types/{id}', [TicketTypeController::class, 'update']);
            Route::delete('/events/{event}/ticket-types/{id}', [TicketTypeController::class, 'destroy']);
        });
});