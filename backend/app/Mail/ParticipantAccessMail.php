<?php

namespace App\Mail;

use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParticipantAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public Participant $participant;
    public ?string $qrImageBase64; // ← This is the base64 string
    public string $emailType;

    /**
     * Create a new message instance.
     */
    public function __construct(Participant $participant, string $qrImageBase64 = null, string $emailType = 'default')
    {
        $this->participant = $participant;
        $this->qrImageBase64 = $qrImageBase64;
        $this->emailType = $emailType;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match($this->emailType) {
                'deleted' => 'Event Registration Cancelled',
                'fair', 'conference' => 'Event Access Confirmed',
                default => 'Event Access Confirmation'
            }
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.participant_access',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}