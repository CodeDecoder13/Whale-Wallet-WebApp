<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The 6-digit code that verifies a new account's email address. */
class VerifyCode extends Mailable
{
    public function __construct(public string $name, public string $code, public bool $reset = false) {}

    public function envelope(): Envelope
    {
        // "Use 123456 to ..." is the subject pattern Gmail recognises as a one-time code
        return new Envelope(subject: "Use {$this->code} to ".($this->reset ? 'reset your Whale Wallet account' : 'verify your Whale Wallet account'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verify-code', text: 'mail.verify-code-text');
    }
}
