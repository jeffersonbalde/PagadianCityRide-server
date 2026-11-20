<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The email with the six digit code that signs a person in.
 */
class LoginCodeMail extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct(public string $code, public int $minutes) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' code: '.$this->code);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(text: 'mail.login-code');
    }
}
