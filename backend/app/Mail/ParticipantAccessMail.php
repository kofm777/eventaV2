<?php

namespace App\Mail;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ParticipantAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $participant;
    public $qrImageBase64;
    public $emailType;
    public $ticketUrl;

    /**
     * Create a new message instance.
     *
     * $ticketUrl is OPTIONAL: when present (guest ticket purchase), the view renders a
     * "Download your ticket" button. Existing 3-arg calls (accept/reject/scan) pass null
     * so those emails are unchanged.
     */
    public function __construct(Participant $participant, string $qrImageBase64 = null, string $emailType = 'default', ?string $ticketUrl = null)
    {
        $this->participant = $participant;
        $this->qrImageBase64 = $qrImageBase64;
        $this->emailType = $emailType;
        $this->ticketUrl = $ticketUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $email = $this->subject(
            match ($this->emailType) {
                'deleted' => 'Event Registration Cancelled',
                'fair', 'conference' => 'Event Access Confirmed',
                default => 'Event Access Confirmation'
            }
        )
        ->view('emails.participant_access')
        ->with([
            'participant' => $this->participant,
            'qrImageBase64' => $this->qrImageBase64,
            'emailType' => $this->emailType,
            'ticketUrl' => $this->ticketUrl,
        ]);

        // QR code attachment is now handled directly in the view using $message->embedData()
        // to ensure proper inline display across all email clients.

        return $email;
    }
}