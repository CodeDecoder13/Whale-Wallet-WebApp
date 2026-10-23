<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The 6-digit code that verifies a new account's email address. */
class VerifyCode extends Mailable
{
    public function __construct(public string $name, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your Whale Wallet code");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verify-code');
    }
}
