<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Password reset email (admins + attendees).
 *
 * Carries a single no-login reset link back to the account's email. The link is the
 * Laravel password-broker token bound to the relevant broker ('admins' | 'attendees').
 * $audience drives only copy ('admin' vs 'attendee'); it does NOT change behaviour.
 * Provider-agnostic (mirrors TicketRetrievalMail) — under MAIL_MAILER=log the rendered
 * link is written to the log, and flipping to SMTP later needs ZERO code change.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $resetUrl Fully-built frontend reset URL (token + email in the query).
     * @param string $audience 'admin' | 'attendee' — copy only.
     */
    public function __construct(public string $resetUrl, public string $audience)
    {
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Reset your password')
            ->view('emails.password_reset')
            ->with([
                'resetUrl' => $this->resetUrl,
                'audience' => $this->audience,
            ]);
    }
}
