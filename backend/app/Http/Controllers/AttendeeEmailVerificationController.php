<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ATTENDEE email verification.
 *
 * verify() is PUBLIC (the user may be logged out when clicking the link); resend() sits
 * behind auth:sanctum-attendee. Issuing/checking is delegated to EmailVerificationService
 * so the register flow can reuse the exact same logic with no duplication. The stored
 * token is sha256(raw) and compared with hash_equals (constant-time); links expire after
 * 48h.
 *
 * NON-ENUMERABLE: verify() runs ONE uniform path — every failure mode (unknown email,
 * already-verified/consumed token, wrong or expired token) returns the SAME generic 422.
 * It deliberately does NOT short-circuit to 200 for an already-verified address: that
 * would be an existence/verified oracle (the token is cleared on first use, so a "nice"
 * 200 on a re-click is indistinguishable from an attacker probing a known address). A
 * legitimately re-clicked link simply reads as "invalid or has expired".
 */
class AttendeeEmailVerificationController extends Controller
{
    public function __construct(private EmailVerificationService $verification)
    {
    }

    /**
     * Public: verify an email from the link (raw token + email in the body). Single
     * uniform path — the service confirms the email owns this exact unexpired token;
     * EVERY failure (unknown email, already-verified, wrong/expired token) returns the
     * same generic 422 so the endpoint is not an existence/verified oracle.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $email = $request->input('email');

        if ($this->verification->verify($email, $request->input('token'))) {
            Log::info('Attendee email verified', ['email' => $email]);

            return response()->json([
                'ok' => true,
                'message' => 'Email verified.',
            ]);
        }

        Log::warning('Attendee email verification failed', ['email' => $email]);

        return response()->json([
            'ok' => false,
            'message' => 'This verification link is invalid or has expired.',
        ], 422);
    }

    /**
     * Authenticated (sanctum-attendee): re-issue the verification email. No-op (still
     * ok:true) if the account is already verified.
     */
    public function resend(Request $request): JsonResponse
    {
        $attendee = $request->user('sanctum-attendee');

        if ($attendee->hasVerifiedEmail()) {
            return response()->json([
                'ok' => true,
                'message' => 'Already verified.',
            ]);
        }

        $raw = $this->verification->issue($attendee);

        $response = [
            'ok' => true,
            'message' => 'Verification email sent.',
        ];

        // Demo/test aid only: echo the raw token so the round-trip works under MAIL_MAILER=log.
        if (config('app.password_reset_debug')) {
            $response['debug_verify_token'] = $raw;
            Log::warning('PASSWORD_RESET_DEBUG echo (attendee resend)', [
                'attendee_id' => $attendee->id,
                'email' => $attendee->email,
            ]);
        }

        return response()->json($response);
    }
}
