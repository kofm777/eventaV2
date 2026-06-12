<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * ADMIN password reset — broker 'admins' (provider Admin, table
 * admin_password_reset_tokens). Fully walled off from attendees: this broker can ONLY
 * mint/consume tokens for the admins table, so an admin reset token can never reset an
 * attendee.
 *
 * NON-ENUMERABLE: forgot() ALWAYS returns the SAME generic ok:true whether or not an
 * admin exists (mirrors TicketRetrievalController). reset() returns a single generic
 * 422 for every failure mode (bad token / unknown user / throttled). A successful reset
 * deletes the admin's Sanctum tokens to force re-login everywhere.
 */
class AdminPasswordResetController extends Controller
{
    /** The generic, always-identical forgot() message (no existence leak). */
    private const GENERIC_FORGOT_MESSAGE = 'If an account exists, a reset link has been sent.';

    /** The generic, non-enumerating reset() failure message. */
    private const GENERIC_RESET_FAILURE = 'This password reset link is invalid or has expired.';

    /**
     * Public: request a reset link. Always generic ok:true (no existence leak).
     */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');
        $admin = Admin::where('email', $email)->first();

        $resetUrl = null;

        if ($admin) {
            // Broker-issued token (broker hashes + stores it in admin_password_reset_tokens).
            $token = Password::broker('admins')->createToken($admin);

            $resetUrl = rtrim(config('app.frontend_url'), '/')
                . '/admin/reset-password?token=' . $token
                . '&email=' . urlencode($email);

            try {
                Mail::to($email)->send(new PasswordResetMail($resetUrl, 'admin'));
            } catch (\Exception $e) {
                // A mail failure must NEVER change the response (no enumeration via errors).
                Log::warning('Failed to send admin password reset email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $response = [
            'ok' => true,
            'message' => self::GENERIC_FORGOT_MESSAGE,
        ];

        // Demo/test aid only: echo the link so the round-trip works under MAIL_MAILER=log.
        if ($admin && config('app.password_reset_debug')) {
            $response['debug_reset_url'] = $resetUrl;
            Log::warning('PASSWORD_RESET_DEBUG echo (admin forgot)', ['email' => $email]);
        }

        return response()->json($response);
    }

    /**
     * Public: consume the token + set a new password. Generic 422 on any failure.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ]);

        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $admin, string $password) {
                // 'password' => 'hashed' cast hashes this on save.
                $admin->password = $password;
                $admin->save();

                // Force re-login everywhere by revoking all Sanctum tokens.
                $admin->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            Log::info('Admin password reset successfully', [
                'email' => $request->input('email'),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Password has been reset.',
            ]);
        }

        Log::warning('Admin password reset failed', [
            'email' => $request->input('email'),
            'status' => $status,
        ]);

        return response()->json([
            'ok' => false,
            'message' => self::GENERIC_RESET_FAILURE,
        ], 422);
    }
}
