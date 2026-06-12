<?php

namespace App\Services;

use App\Mail\EmailVerificationMail;
use App\Models\Attendee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Attendee email-verification helper — the single, reusable place that issues and
 * checks verify tokens, so the controller AND the register flow share ONE implementation
 * (no duplication).
 *
 * The raw token is returned/handled in the clear ONLY long enough to build the link;
 * what is PERSISTED is sha256(raw) (never the plaintext), mirroring how reset tokens are
 * stored hashed. Mail send is wrapped in try/catch + Log::warning (like
 * TicketRetrievalController / OrderService) so a mail failure never bubbles up.
 */
class EmailVerificationService
{
    /** Verify links are valid for 48 hours from issue. */
    public const EXPIRY_HOURS = 48;

    /**
     * Issue (or re-issue) a verification token for the attendee, persist its sha256 hash,
     * send the verification email (best-effort), and return the RAW token (for the
     * PASSWORD_RESET_DEBUG echo). Never throws on a mail failure.
     */
    public function issue(Attendee $attendee): string
    {
        $raw = Str::random(64);

        $attendee->email_verification_token = hash('sha256', $raw);
        $attendee->email_verification_sent_at = now();
        $attendee->save();

        $verifyUrl = rtrim(config('app.frontend_url'), '/')
            . '/attendee/verify-email?token=' . $raw
            . '&email=' . urlencode($attendee->email);

        try {
            Mail::to($attendee->email)->send(new EmailVerificationMail($verifyUrl));
        } catch (\Exception $e) {
            // A mail failure must NEVER fail the caller (signup/resend).
            Log::warning('Failed to send attendee email-verification email', [
                'attendee_id' => $attendee->id,
                'email' => $attendee->email,
                'error' => $e->getMessage(),
            ]);
        }

        return $raw;
    }

    /**
     * Constant-time check of a raw token for the given email. Returns true and FLIPS the
     * attendee to verified (clearing the token) on success; false otherwise. Already-
     * verified accounts are handled idempotently by the caller, not here.
     */
    public function verify(string $email, string $token): bool
    {
        $attendee = Attendee::where('email', $email)->first();

        if (
            ! $attendee
            || $attendee->email_verification_token === null
            || $attendee->email_verification_sent_at === null
            || $attendee->email_verification_sent_at->lt(now()->subHours(self::EXPIRY_HOURS))
        ) {
            return false;
        }

        if (! hash_equals($attendee->email_verification_token, hash('sha256', $token))) {
            return false;
        }

        $attendee->email_verified_at = now();
        $attendee->email_verification_token = null;
        $attendee->email_verification_sent_at = null;
        $attendee->save();

        return true;
    }
}
