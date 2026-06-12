<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Phase 2: surface issuance domain errors as 422 JSON (sold-out / at-capacity).
        // The free /register path catches these inline; this covers the (Phase 3) paid
        // purchase path + any future caller uniformly.
        $this->renderable(function (InventoryExceededException|CapacityExceededException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return null;
        });
    }
}
