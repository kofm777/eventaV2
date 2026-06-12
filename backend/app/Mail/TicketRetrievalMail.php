<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * PHASE 4 — find-my-tickets magic-link email.
 *
 * Carries one or more no-login /ticket/{token} links (one per matched PAID order) back
 * to the buyer's own email. Issues NO new tokens and changes NO order state — it simply
 * re-sends the EXISTING orders.ticket_download_token links. Under MAIL_MAILER=log the
 * rendered links are written to the log until real SMTP is configured.
 *
 * @var array<int, array{order_number: string, event_name: string|null, url: string}> $links
 */
class TicketRetrievalMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{order_number: string, event_name: string|null, url: string}> $links
     */
    public function __construct(public array $links)
    {
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your ticket link(s)')
            ->view('emails.ticket_retrieval')
            ->with(['links' => $this->links]);
    }
}
