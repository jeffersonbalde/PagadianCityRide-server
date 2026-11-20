<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A short message to a person who can be reached by email, such as the answer to a driver application.
 */
class PlainMessageMail extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct(public string $title, public string $body) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(text: 'mail.plain-message');
    }
}
