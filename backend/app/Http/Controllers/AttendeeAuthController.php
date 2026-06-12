<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendeeLoginRequest;
use App\Http\Requests\AttendeeRegisterRequest;
use App\Models\Attendee;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PHASE 6 — attendee (public account) auth. Mirrors AuthController's shape but issues
 * ATTENDEE Sanctum tokens (tokenable_type = App\Models\Attendee) instead of admin
 * tokens. These accounts are pure opt-in convenience layered on top of the unchanged
 * guest checkout + magic-link find-my-tickets flows.
 */
class AttendeeAuthController extends Controller
{
    /**
     * Public attendee signup. Creates an Attendee (password auto-hashed by the model
     * `hashed` cast), issues a fresh token, returns the standard {ok, attendee, token}.
     */
    public function register(AttendeeRegisterRequest $request, EmailVerificationService $verification): JsonResponse
    {
        $attendee = Attendee::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            // 'password' => 'hashed' cast on Attendee hashes this automatically.
            'password' => $request->validated('password'),
            'phone' => $request->validated('phone'),
        ]);

        $token = $attendee->createToken('attendee-token')->plainTextToken;

        // Fire the email-verification email (best-effort). A mail failure must NOT fail
        // signup — the service swallows + logs send errors internally, and we still wrap
        // the whole call defensively.
        try {
            $verification->issue($attendee);
        } catch (\Exception $e) {
            Log::warning('Failed to issue attendee verification on register', [
                'attendee_id' => $attendee->id,
                'email' => $attendee->email,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Attendee registered', [
            'attendee_id' => $attendee->id,
            'email' => $attendee->email,
        ]);

        return response()->json([
            'ok' => true,
            'attendee' => $attendee->only(['id', 'name', 'email', 'phone'])
                + ['email_verified' => $attendee->hasVerifiedEmail()],
            'token' => $token,
            'message' => 'Account created.',
        ], 201);
    }

    /**
     * Attendee login. Generic invalid-credentials error (no existence leak) + a
     * Log::warning on failure, matching AuthController. Revokes existing tokens and
     * mints a fresh one on success.
     */
    public function login(AttendeeLoginRequest $request): JsonResponse
    {
        $attendee = Attendee::where('email', $request->validated('email'))->first();

        if (! $attendee || ! Hash::check($request->validated('password'), $attendee->password)) {
            Log::warning('Failed attendee login attempt', [
                'email' => $request->validated('email'),
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Les informations d\'identification fournies sont incorrectes.'],
            ]);
        }

        // Revoke existing tokens (matching AuthController), then mint a fresh one.
        $attendee->tokens()->delete();

        $token = $attendee->createToken('attendee-token')->plainTextToken;

        Log::info('Attendee logged in successfully', [
            'attendee_id' => $attendee->id,
            'email' => $attendee->email,
        ]);

        return response()->json([
            'ok' => true,
            'attendee' => $attendee->only(['id', 'name', 'email', 'phone'])
                + ['email_verified' => $attendee->hasVerifiedEmail()],
            'token' => $token,
            'message' => 'Connexion réussie.',
        ]);
    }

    /**
     * Attendee logout — revoke only the current token (explicit sanctum-attendee guard).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum-attendee')->currentAccessToken()->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Current attendee profile (explicit sanctum-attendee guard).
     */
    public function me(Request $request): JsonResponse
    {
        $attendee = $request->user('sanctum-attendee');

        return response()->json([
            'ok' => true,
            'attendee' => $attendee->only(['id', 'name', 'email', 'phone'])
                + ['email_verified' => $attendee->hasVerifiedEmail()],
        ]);
    }
}
