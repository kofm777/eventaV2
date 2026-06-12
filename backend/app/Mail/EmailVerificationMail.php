<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Attendee email-verification email.
 *
 * Carries a single no-login verify link (raw token + email in the query) back to the
 * attendee's email. Provider-agnostic (mirrors TicketRetrievalMail) — under
 * MAIL_MAILER=log the rendered link is written to the log, and flipping to SMTP later
 * needs ZERO code change.
 */
class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $verifyUrl Fully-built frontend verify URL (raw token + email in the query).
     */
    public function __construct(public string $verifyUrl)
    {
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Verify your email')
            ->view('emails.email_verification')
            ->with(['verifyUrl' => $this->verifyUrl]);
    }
}
