<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otpCode;
    public $userName;
    public $expiresIn;
    public $isInvitation;

    /**
     * Create a new message instance.
     */
    public function __construct(string $otpCode, string $userName = null, int $expiresIn = 10, bool $isInvitation = false)
    {
        $this->otpCode = $otpCode;
        $this->userName = $userName;
        $this->expiresIn = $expiresIn;
        $this->isInvitation = $isInvitation;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isInvitation 
            ? 'Code de confirmation - Invitation Club Privilèges'
            : 'Code de connexion - Club Privilèges Dashboard';
            
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.otp',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}