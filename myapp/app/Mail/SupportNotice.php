<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/** "New support message" (to the admins) or "Support replied" (to the user): who wrote, a short preview, and a button to open the chat. */
class SupportNotice extends Mailable
{
    public string $preview;

    public function __construct(public string $name, public string $headline, string $body, public string $url, public string $cta)
    {
        $this->preview = Str::limit($body, 300);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.support', text: 'mail.support-text');
    }
}
